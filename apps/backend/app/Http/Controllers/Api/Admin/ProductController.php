<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\LoadsProductDetail;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\ListProductsRequest;
use App\Http\Requests\Api\Admin\ProductStoreRequest;
use App\Http\Requests\Api\Admin\ProductUpdateRequest;
use App\Http\Resources\Admin\ProductDetailResource;
use App\Http\Resources\Admin\ProductResource;
use App\Models\Product;
use App\Services\VariantGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The catalogue side of the panel.
 *
 * Two rules shape everything here. The first is the Fase 1 invariant that
 * every product has at least one variant — which is why creating a product
 * creates one, and why a product can never be left with none. The second is
 * that a product is archived, never deleted: `order_items` points at its
 * variants, and a store has to stay able to answer what it sold last March.
 */
class ProductController extends Controller
{
    use LoadsProductDetail;

    public function index(ListProductsRequest $request): AnonymousResourceCollection
    {
        $products = Product::query()
            ->with([
                'category',
                'images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('position')->orderBy('id'),
            ])
            ->withCount(['variants', 'options', 'images'])
            // Stock lives on the variants, so the catalogue screen has to add
            // it up to show anything at the product level.
            ->withSum('variants', 'stock')
            ->when(
                $request->input('trashed') === 'with',
                fn (Builder $query) => $query->withTrashed(),
            )
            ->when(
                $request->input('trashed') === 'only',
                fn (Builder $query) => $query->onlyTrashed(),
            )
            ->when(
                $request->filled('search'),
                fn (Builder $query) => $this->applySearch($query, (string) $request->string('search')),
            )
            ->when(
                $request->filled('category_id'),
                fn (Builder $query) => $query->where('category_id', $request->integer('category_id')),
            )
            ->when(
                $request->filled('status'),
                fn (Builder $query) => $query->where('is_active', $request->input('status') === 'active'),
            )
            ->orderBy('name')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return ProductResource::collection($products);
    }

    public function show(Product $product): JsonResponse
    {
        return ProductDetailResource::make($this->productDetail($product))->response();
    }

    /**
     * Creating a product creates its implicit variant in the same transaction:
     * a product with no variants is unsellable and, per the Fase 1 rule, does
     * not exist as a state the catalogue is allowed to be in. The admin can
     * turn it into an optioned product later — the generator replaces the
     * implicit variant when it does.
     */
    public function store(ProductStoreRequest $request, VariantGenerator $generator): JsonResponse
    {
        $product = DB::transaction(function () use ($request, $generator) {
            // `is_active` is set explicitly rather than left to the column
            // default, so the response carries it without a round-trip to
            // re-read the row.
            $product = Product::create([
                ...$request->validated(),
                'is_active' => $request->boolean('is_active', true),
            ]);

            $generator->generate($product);

            return $product;
        });

        return ProductDetailResource::make($this->productDetail($product))
            ->response()
            ->setStatusCode(201);
    }

    public function update(ProductUpdateRequest $request, Product $product): JsonResponse
    {
        $product->update($request->validated());

        return ProductDetailResource::make($this->productDetail($product->fresh()))->response();
    }

    /**
     * A soft delete that takes the product's live variants with it, so nothing
     * stays sellable through its id while the product is gone.
     *
     * @throws ValidationException
     */
    public function destroy(Product $product): JsonResponse
    {
        if ($product->hasLiveReservations()) {
            throw ValidationException::withMessages([
                'product' => [
                    'Este producto tiene unidades reservadas por órdenes abiertas. '.
                    'Resuelve esas órdenes antes de archivarlo.',
                ],
            ]);
        }

        $product->archive();

        return ProductDetailResource::make($this->productDetail($product->fresh()))->response();
    }

    /**
     * Brings back the product and exactly the variants this archiving took —
     * not the ones the admin had deleted one by one beforehand.
     */
    public function restore(Product $product): JsonResponse
    {
        if ($product->trashed()) {
            $product->unarchive();
        }

        return ProductDetailResource::make($this->productDetail($product->fresh()))->response();
    }

    /**
     * Name and SKU: what an admin has at hand when a customer describes a
     * product or a warehouse note quotes a code.
     *
     * The term is escaped because `%` and `_` are wildcards to ILIKE —
     * without it, a search for `_` would match the whole catalogue.
     */
    private function applySearch(Builder $query, string $term): Builder
    {
        $pattern = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(function (Builder $query) use ($pattern) {
            $query->where('name', 'ilike', $pattern)
                ->orWhere('slug', 'ilike', $pattern)
                ->orWhereHas('variants', fn (Builder $variants) => $variants->where('sku', 'ilike', $pattern));
        });
    }
}
