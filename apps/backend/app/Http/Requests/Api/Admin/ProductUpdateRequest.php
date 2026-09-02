<?php

namespace App\Http\Requests\Api\Admin;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            // Renaming a product deliberately does NOT re-slug it: the slug is
            // the storefront URL a customer may already have bookmarked or a
            // seller may have pasted into a chat. Changing it is a separate,
            // explicit act.
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('products', 'slug')->ignore($this->product()->getKey()),
            ],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'base_price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:999999999999', 'decimal:0,6'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.unique' => 'Ya existe un producto con esa URL.',
            'slug.alpha_dash' => 'La URL solo puede tener letras, números y guiones.',
            'base_price.decimal' => 'El precio admite hasta 6 decimales.',
            'category_id.exists' => 'Esa categoría no existe.',
        ];
    }

    private function product(): Product
    {
        return $this->route('product');
    }
}
