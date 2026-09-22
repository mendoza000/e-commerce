<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Turns a product's options into the rows the store actually sells.
 *
 * The Fase 1 rule this exists to uphold: every product has at least one
 * variant, and a product with no options has exactly one — the implicit
 * variant, which is the product itself wearing a SKU. That is why creating a
 * product creates a variant, and why generating real combinations archives the
 * implicit one: once "Rojo" and "Azul" exist, a variant that is neither is not
 * something anyone can order.
 *
 * Generating is idempotent: a combination that already exists is counted as
 * skipped, never duplicated. That is what lets the panel offer "generate the
 * rest" after the admin adds one more value to an option.
 */
class VariantGenerator
{
    /**
     * @param  array<int, array<int, int>>|null  $combinations  Each entry is a set of product_option_value_id. Null means every combination.
     * @return array{created: Collection<int, ProductVariant>, skipped: int, archived_implicit: int}
     *
     * @throws ValidationException
     */
    public function generate(Product $product, ?array $combinations = null, ?string $skuPrefix = null): array
    {
        $product->load([
            'options' => fn ($query) => $query->orderBy('position')->orderBy('id'),
            'options.values',
        ]);

        $options = $product->options;

        $wanted = $options->isEmpty()
            ? $this->implicitOnly($combinations)
            : $this->resolveCombinations($options, $combinations);

        return DB::transaction(function () use ($product, $options, $wanted, $skuPrefix) {
            $existing = $this->existingSignatures($product);

            $implicit = $options->isEmpty()
                ? collect()
                : $product->variants()->whereDoesntHave('optionValues')->get();

            // Refused before anything is written: a product whose implicit
            // variant is holding units for an open order must not be turned
            // into an optioned product underneath that customer.
            if ($implicit->isNotEmpty()) {
                $this->guardImplicitIsFree($implicit);
            }

            $created = collect();
            $skipped = 0;

            foreach ($wanted as $combination) {
                if ($existing->has($this->signature($combination))) {
                    $skipped++;

                    continue;
                }

                $created->push($this->createVariant($product, $combination, $skuPrefix));
            }

            // Only archived once there is something to sell in its place: a
            // run that created nothing must not leave the product with zero
            // variants.
            $archived = 0;

            if ($created->isNotEmpty() && $implicit->isNotEmpty()) {
                $implicit->each(fn (ProductVariant $variant) => $variant->delete());

                $archived = $implicit->count();
            }

            return ['created' => $created, 'skipped' => $skipped, 'archived_implicit' => $archived];
        });
    }

    /**
     * A product with no options: the only variant that can exist is the
     * implicit one, so an explicit combination is a contradiction rather than
     * a request to honour.
     *
     * @param  array<int, array<int, int>>|null  $combinations
     * @return array<int, array<int, int>>
     */
    private function implicitOnly(?array $combinations): array
    {
        $asked = array_filter($combinations ?? [], fn (array $combination) => $combination !== []);

        if ($asked !== []) {
            throw ValidationException::withMessages([
                'combinations' => ['Este producto no tiene opciones configuradas: solo admite una variante sin opciones.'],
            ]);
        }

        return [[]];
    }

    /**
     * @param  Collection<int, ProductOption>  $options
     * @param  array<int, array<int, int>>|null  $combinations
     * @return array<int, array<int, int>>
     */
    private function resolveCombinations(Collection $options, ?array $combinations): array
    {
        $valueIdsByOption = $this->valueIdsByOption($options);

        $resolved = $combinations === null
            ? $this->cartesian($valueIdsByOption)
            : $this->validateCombinations($options, $valueIdsByOption, $combinations);

        $max = (int) config('commerce.catalog.max_variants_per_product');

        if (count($resolved) > $max) {
            throw ValidationException::withMessages([
                'combinations' => [
                    'Esa selección genera '.count($resolved)." variantes y el máximo por producto es {$max}. ".
                    'Genera un subconjunto de combinaciones.',
                ],
            ]);
        }

        return $resolved;
    }

    /**
     * @param  Collection<int, ProductOption>  $options
     * @return array<int, array<int, int>> Value ids grouped by option, in option order.
     */
    private function valueIdsByOption(Collection $options): array
    {
        $empty = $options->first(fn (ProductOption $option) => $option->values->isEmpty());

        if ($empty !== null) {
            throw ValidationException::withMessages([
                'combinations' => ["La opción \"{$empty->name}\" todavía no tiene valores: agrégalos antes de generar variantes."],
            ]);
        }

        return $options
            ->map(fn (ProductOption $option) => $option->values
                ->sortBy([['position', 'asc'], ['id', 'asc']])
                ->pluck('id')
                ->all())
            ->values()
            ->all();
    }

    /**
     * Every combination the options allow, in option order — the "generate
     * all" case. Seeded with one empty combination so a single option still
     * produces one variant per value.
     *
     * @param  array<int, array<int, int>>  $valueIdsByOption
     * @return array<int, array<int, int>>
     */
    private function cartesian(array $valueIdsByOption): array
    {
        return array_reduce(
            $valueIdsByOption,
            function (array $carry, array $valueIds) {
                $result = [];

                foreach ($carry as $combination) {
                    foreach ($valueIds as $valueId) {
                        $result[] = [...$combination, $valueId];
                    }
                }

                return $result;
            },
            [[]],
        );
    }

    /**
     * A combination picked by hand has to be a real point in the grid: exactly
     * one value per option, every value belonging to this product. Anything
     * else produces a variant the storefront cannot resolve from what a
     * customer selected.
     *
     * @param  Collection<int, ProductOption>  $options
     * @param  array<int, array<int, int>>  $valueIdsByOption
     * @param  array<int, array<int, int>>  $combinations
     * @return array<int, array<int, int>>
     */
    private function validateCombinations(Collection $options, array $valueIdsByOption, array $combinations): array
    {
        $optionOfValue = [];

        foreach ($options as $option) {
            foreach ($option->values as $value) {
                $optionOfValue[$value->id] = $option->id;
            }
        }

        $errors = [];
        $resolved = [];
        $seen = [];

        foreach (array_values($combinations) as $index => $combination) {
            $valueIds = array_values(array_unique(array_map('intval', $combination)));

            if (array_diff($valueIds, array_keys($optionOfValue)) !== []) {
                $errors["combinations.{$index}"] = ['Esa combinación usa valores que no pertenecen a este producto.'];

                continue;
            }

            $covered = array_unique(array_map(fn (int $valueId) => $optionOfValue[$valueId], $valueIds));

            if (count($covered) !== $options->count() || count($valueIds) !== $options->count()) {
                $errors["combinations.{$index}"] = ['Cada combinación debe indicar exactamente un valor por cada opción del producto.'];

                continue;
            }

            $signature = $this->signature($valueIds);

            // A duplicate inside one request would otherwise be created twice:
            // the "already exists" check reads the database, which does not
            // yet know about the row two iterations back.
            if (isset($seen[$signature])) {
                $errors["combinations.{$index}"] = ['Esa combinación está repetida en la solicitud.'];

                continue;
            }

            $seen[$signature] = true;
            $resolved[] = $valueIds;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        // Put back in option order so the SKU reads PRODUCTO-COLOR-TALLA
        // whatever order the panel happened to send the ids in.
        return array_map(
            fn (array $valueIds) => $this->inOptionOrder($valueIds, $valueIdsByOption),
            $resolved,
        );
    }

    /**
     * @param  array<int, int>  $valueIds
     * @param  array<int, array<int, int>>  $valueIdsByOption
     * @return array<int, int>
     */
    private function inOptionOrder(array $valueIds, array $valueIdsByOption): array
    {
        $ordered = [];

        foreach ($valueIdsByOption as $optionValueIds) {
            foreach ($valueIds as $valueId) {
                if (in_array($valueId, $optionValueIds, true)) {
                    $ordered[] = $valueId;

                    break;
                }
            }
        }

        return $ordered;
    }

    /**
     * The option-value sets of the variants this product already has, so a
     * second run adds only what is missing.
     *
     * @return Collection<string, true>
     */
    private function existingSignatures(Product $product): Collection
    {
        return $product->variants()
            ->with('optionValues')
            ->get()
            ->mapWithKeys(fn (ProductVariant $variant) => [
                $this->signature($variant->optionValues->pluck('id')->all()) => true,
            ]);
    }

    /**
     * @param  array<int, int>  $valueIds
     */
    private function signature(array $valueIds): string
    {
        $ids = array_map('intval', $valueIds);
        sort($ids);

        return implode('-', $ids);
    }

    /**
     * @param  array<int, int>  $valueIds
     */
    private function createVariant(Product $product, array $valueIds, ?string $skuPrefix): ProductVariant
    {
        $values = ProductOptionValue::query()->findMany($valueIds)->keyBy('id');

        $variant = $product->variants()->create([
            'sku' => $this->uniqueSku($product, $valueIds, $values, $skuPrefix),
            'price_override' => null,
            // A new variant starts empty on purpose: units enter through an
            // inventory adjustment, which is the call that writes the kardex.
            'stock' => 0,
            'reserved_quantity' => 0,
            'is_active' => true,
        ]);

        if ($valueIds !== []) {
            $variant->optionValues()->attach($valueIds);
        }

        return $variant;
    }

    /**
     * A readable SKU derived from the product and the combination —
     * CAMISA-ROJO-M — rather than a random string, because a SKU gets read out
     * loud in a warehouse. The admin can rename it afterwards.
     *
     * Collisions are settled with a numeric suffix, checked against archived
     * variants too: the unique index does not forget a soft-deleted row.
     *
     * @param  array<int, int>  $valueIds
     * @param  Collection<int, ProductOptionValue>  $values
     */
    private function uniqueSku(Product $product, array $valueIds, Collection $values, ?string $skuPrefix): string
    {
        $base = $this->skuSegment($skuPrefix ?? $product->slug, 20) ?: 'SKU';

        foreach ($valueIds as $valueId) {
            $segment = $this->skuSegment((string) $values->get($valueId)?->value, 10);

            if ($segment !== '') {
                $base .= '-'.$segment;
            }
        }

        $sku = $base;
        $suffix = 2;

        while (ProductVariant::withTrashed()->where('sku', $sku)->exists()) {
            $sku = $base.'-'.$suffix++;
        }

        return $sku;
    }

    private function skuSegment(string $value, int $length): string
    {
        return Str::upper(Str::limit(Str::slug($value), $length, ''));
    }

    /**
     * @param  Collection<int, ProductVariant>  $implicit
     */
    private function guardImplicitIsFree(Collection $implicit): void
    {
        $reserved = $implicit->first(fn (ProductVariant $variant) => $variant->hasLiveReservations());

        if ($reserved !== null) {
            throw ValidationException::withMessages([
                'combinations' => [
                    'La variante sin opciones de este producto tiene unidades reservadas por órdenes abiertas. '.
                    'Resuelve esas órdenes antes de convertirlo en un producto con variantes.',
                ],
            ]);
        }
    }
}
