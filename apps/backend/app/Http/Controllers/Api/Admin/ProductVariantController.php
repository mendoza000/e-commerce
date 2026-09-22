<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\LoadsProductDetail;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\AdjustStockRequest;
use App\Http\Requests\Api\Admin\GenerateVariantsRequest;
use App\Http\Requests\Api\Admin\VariantUpdateRequest;
use App\Http\Resources\Admin\ProductDetailResource;
use App\Http\Resources\Admin\ProductVariantResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\InventoryReservationService;
use App\Services\VariantGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Variants are the unit the store actually sells: own SKU, own stock,
 * optionally its own price.
 *
 * Note what this controller does *not* let anyone do: set `stock`. Every unit
 * that moves has to leave a kardex row saying who moved it and why, so stock
 * changes go through adjustStock() and nowhere else. A plain `stock` field on
 * the edit form would be a way around the ledger.
 */
class ProductVariantController extends Controller
{
    use LoadsProductDetail;

    /**
     * Creates the combinations the admin asked for — all of them, or the
     * selection the panel sent — skipping the ones that already exist.
     *
     * Answers with the whole product so the editor can redraw its variant
     * table, plus a `meta` block saying what actually happened: "generated 6,
     * 4 already existed" is the feedback that makes a second run safe to try.
     */
    public function generate(
        GenerateVariantsRequest $request,
        Product $product,
        VariantGenerator $generator,
    ): JsonResponse {
        $result = $generator->generate(
            $product,
            $request->input('combinations'),
            $request->input('sku_prefix'),
        );

        return ProductDetailResource::make($this->productDetail($product->fresh()))
            ->additional([
                'meta' => [
                    'created' => $result['created']->count(),
                    'skipped' => $result['skipped'],
                    'archived_implicit' => $result['archived_implicit'],
                ],
            ])
            ->response()
            ->setStatusCode(201);
    }

    public function update(VariantUpdateRequest $request, ProductVariant $variant): JsonResponse
    {
        $variant->update($request->validated());

        return ProductVariantResource::make($this->variantDetail($variant->fresh()))->response();
    }

    /**
     * Archiving a single variant, for the combination a store stops carrying.
     *
     * Two refusals: one for units already promised to open orders, and one for
     * the product's last live variant — a product with none is unsellable, and
     * the way to retire a whole product is to archive the product.
     *
     * @throws ValidationException
     */
    public function destroy(ProductVariant $variant): JsonResponse
    {
        if ($variant->hasLiveReservations()) {
            throw ValidationException::withMessages([
                'variant' => [
                    'Esta variante tiene unidades reservadas por órdenes abiertas. '.
                    'Resuelve esas órdenes antes de archivarla.',
                ],
            ]);
        }

        $isOnlyOne = ProductVariant::query()
            ->where('product_id', $variant->product_id)
            ->whereKeyNot($variant->getKey())
            ->doesntExist();

        if ($isOnlyOne) {
            throw ValidationException::withMessages([
                'variant' => [
                    'Es la única variante del producto y todo producto debe tener al menos una. '.
                    'Archiva el producto completo si ya no lo vendes.',
                ],
            ]);
        }

        $variant->delete();

        return response()->json(status: 204);
    }

    /**
     * The only way stock changes by hand. Delegates to the service, which
     * takes the row lock, refuses to leave stock below what open orders have
     * reserved, and writes the Adjustment row in the kardex.
     */
    public function adjustStock(
        AdjustStockRequest $request,
        ProductVariant $variant,
        InventoryReservationService $inventory,
    ): JsonResponse {
        $adjusted = $inventory->adjust(
            $variant,
            $request->integer('quantity_change'),
            (string) $request->string('reason'),
            $this->admin($request),
        );

        return ProductVariantResource::make($this->variantDetail($adjusted))->response();
    }

    private function variantDetail(ProductVariant $variant): ProductVariant
    {
        return $variant->load(['product', 'optionValues.option']);
    }

    /**
     * The route group guarantees an authenticated, active admin; this only
     * narrows the type for the service, which records who adjusted the stock.
     */
    private function admin(Request $request): User
    {
        return $request->user();
    }
}
