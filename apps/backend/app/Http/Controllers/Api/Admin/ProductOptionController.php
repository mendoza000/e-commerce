<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\ProductOptionStoreRequest;
use App\Http\Requests\Api\Admin\ProductOptionUpdateRequest;
use App\Http\Resources\Admin\ProductOptionResource;
use App\Models\Product;
use App\Models\ProductOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Options are the axes of a product's variant grid: "Color", "Talla". They are
 * free-form on purpose — the PRD forbids hardcoded `color` / `talla` columns,
 * because the next store sells something else entirely.
 *
 * Adding and removing them changes what a variant *means*, which is why both
 * are refused while variants are built on the grid. Renaming one does not, so
 * it is always allowed.
 */
class ProductOptionController extends Controller
{
    public function store(ProductOptionStoreRequest $request, Product $product): JsonResponse
    {
        $attributes = $request->validated();

        $option = $product->options()->create([
            'name' => $attributes['name'],
            // Appended to the end unless the panel says otherwise: option
            // order is what decides the order of a SKU's segments.
            'position' => $attributes['position'] ?? (int) $product->options()->max('position') + 1,
        ]);

        return ProductOptionResource::make($option->load('values'))
            ->response()
            ->setStatusCode(201);
    }

    public function update(ProductOptionUpdateRequest $request, ProductOption $option): JsonResponse
    {
        $option->update($request->validated());

        return ProductOptionResource::make($option->fresh()->load('values'))->response();
    }

    /**
     * `product_option_values` cascades into `variant_option_values`, so this
     * would not fail on a product with variants — it would quietly strip the
     * value that told them apart, leaving two identical rows where "Rojo" and
     * "Azul" used to be.
     *
     * @throws ValidationException
     */
    public function destroy(ProductOption $option): JsonResponse
    {
        if ($option->isUsedByVariants()) {
            throw ValidationException::withMessages([
                'option' => [
                    'Hay variantes construidas sobre esta opción. Elimina esas variantes antes de eliminarla.',
                ],
            ]);
        }

        $option->delete();

        return response()->json(status: 204);
    }
}
