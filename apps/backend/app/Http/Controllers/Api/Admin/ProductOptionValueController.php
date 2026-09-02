<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\ProductOptionValueStoreRequest;
use App\Http\Requests\Api\Admin\ProductOptionValueUpdateRequest;
use App\Http\Resources\Admin\ProductOptionValueResource;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * The possible values of an option: "Rojo", "Azul", "M", "L".
 *
 * Adding one is always safe — existing variants stay well defined and the
 * generator picks up the new combinations on its next run. Removing one is
 * not, for the same reason removing an option is not.
 */
class ProductOptionValueController extends Controller
{
    public function store(ProductOptionValueStoreRequest $request, ProductOption $option): JsonResponse
    {
        $attributes = $request->validated();

        $value = $option->values()->create([
            'value' => $attributes['value'],
            'position' => $attributes['position'] ?? (int) $option->values()->max('position') + 1,
        ]);

        return ProductOptionValueResource::make($value)
            ->response()
            ->setStatusCode(201);
    }

    public function update(ProductOptionValueUpdateRequest $request, ProductOptionValue $optionValue): JsonResponse
    {
        $optionValue->update($request->validated());

        return ProductOptionValueResource::make($optionValue->fresh())->response();
    }

    /**
     * Refused while a live variant is built on it. Images pinned to this value
     * are not a reason to refuse: `product_images.product_option_value_id` is
     * `nullOnDelete`, so they fall back to being photos of the product in
     * general, which is exactly what they become.
     *
     * @throws ValidationException
     */
    public function destroy(ProductOptionValue $optionValue): JsonResponse
    {
        if ($optionValue->isUsedByVariants()) {
            throw ValidationException::withMessages([
                'option_value' => [
                    'Hay variantes que usan este valor. Elimina esas variantes antes de eliminarlo.',
                ],
            ]);
        }

        $optionValue->delete();

        return response()->json(status: 204);
    }
}
