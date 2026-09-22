<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReorderProductImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The whole list, in the order it should be shown. That the list covers
     * exactly this product's images is checked in ProductImageService: a
     * partial list would leave the images it omits sharing positions with the
     * ones it moved.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'images' => ['required', 'array', 'min:1', 'max:'.config('commerce.catalog.max_images_per_product')],
            'images.*' => ['integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'images.required' => 'Debes enviar el orden de las imágenes.',
        ];
    }
}
