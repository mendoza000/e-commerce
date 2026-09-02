<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ProductImageStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `image` on top of `mimes` on purpose: `mimes` trusts the extension, and
     * `image` makes the validator actually look at the file. A .jpg that is
     * really a PHP script must not reach a public disk.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'image' => [
                'required',
                'file',
                'image',
                'mimes:'.implode(',', config('commerce.product_image.mimes')),
                'max:'.config('commerce.product_image.max_kilobytes'),
            ],
            // Null means "this photo is of the product in general". Whether the
            // value actually belongs to this product is checked in
            // ProductImageService, which owns that relationship.
            'product_option_value_id' => ['nullable', 'integer', 'exists:product_option_values,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxMegabytes = round(((int) config('commerce.product_image.max_kilobytes')) / 1024, 1);

        return [
            'image.required' => 'Debes adjuntar una imagen.',
            'image.image' => 'El archivo no es una imagen válida.',
            'image.mimes' => 'La imagen debe ser JPG, PNG o WEBP.',
            'image.max' => "La imagen no puede pesar más de {$maxMegabytes} MB.",
            'product_option_value_id.exists' => 'Ese valor de opción no existe.',
        ];
    }
}
