<?php

namespace App\Http\Requests\Api\Admin;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorised by the route group's `role:owner` middleware: catalogue
        // writing is owner territory (docs/decisions.md).
        return true;
    }

    /**
     * Nobody is asked to invent a slug. When the panel does not send one it is
     * derived from the name here, already free of collisions, so the unique
     * rule below never fails on a slug the admin did not choose.
     */
    protected function prepareForValidation(): void
    {
        if (blank($this->input('slug')) && filled($this->input('name'))) {
            $this->merge(['slug' => Product::uniqueSlug((string) $this->input('name'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // The unique rule counts archived products too, exactly like the
            // database index does.
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('products', 'slug')],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:5000'],
            // decimal(18,6): twelve digits of integer part, six of fraction.
            'base_price' => ['required', 'numeric', 'min:0', 'max:999999999999', 'decimal:0,6'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del producto es obligatorio.',
            'slug.unique' => 'Ya existe un producto con esa URL.',
            'slug.alpha_dash' => 'La URL solo puede tener letras, números y guiones.',
            'base_price.required' => 'El precio base es obligatorio.',
            'base_price.decimal' => 'El precio admite hasta 6 decimales.',
            'category_id.exists' => 'Esa categoría no existe.',
        ];
    }
}
