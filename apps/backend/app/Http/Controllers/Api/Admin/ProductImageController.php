<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\ProductImageStoreRequest;
use App\Http\Requests\Api\Admin\ReorderProductImagesRequest;
use App\Http\Resources\ProductImageResource;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ProductImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Product photos, optionally pinned to one of the product's option values.
 *
 * Pinning to the *value* rather than to the variant is the point (PRD,
 * catálogo): the photos of "Rojo" are inherited by Rojo-38, Rojo-39 and
 * Rojo-40 without anyone uploading them three times. A product with no visual
 * option just leaves the value null.
 *
 * Every action answers with the product's full image list, in display order,
 * because every one of them can change that order or which image is primary.
 */
class ProductImageController extends Controller
{
    public function index(Product $product, ProductImageService $images): AnonymousResourceCollection
    {
        return ProductImageResource::collection($images->ordered($product));
    }

    public function store(
        ProductImageStoreRequest $request,
        Product $product,
        ProductImageService $images,
    ): JsonResponse {
        $images->store(
            $product,
            $request->file('image'),
            $request->input('product_option_value_id') !== null
                ? $request->integer('product_option_value_id')
                : null,
        );

        return ProductImageResource::collection($images->ordered($product))
            ->response()
            ->setStatusCode(201);
    }

    public function reorder(
        ReorderProductImagesRequest $request,
        Product $product,
        ProductImageService $images,
    ): JsonResponse {
        $ordered = $images->reorder($product, $request->input('images'));

        return ProductImageResource::collection($ordered)->response();
    }

    /**
     * The cover the storefront shows. Exactly one per product: the service
     * clears the flag from the others in the same transaction.
     */
    public function makePrimary(ProductImage $image, ProductImageService $images): JsonResponse
    {
        return ProductImageResource::collection($images->makePrimary($image))->response();
    }

    public function destroy(ProductImage $image, ProductImageService $images): JsonResponse
    {
        $product = $image->product;

        $images->delete($image);

        return ProductImageResource::collection($images->ordered($product))->response();
    }
}
