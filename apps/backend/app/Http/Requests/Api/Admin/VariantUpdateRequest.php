<?php

namespace App\Http\Requests\Api\Admin;

use App\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VariantUpdateRequest extends FormRequest
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
            'sku' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                // Not scoped to the product: a SKU is a warehouse-wide
                // identifier, and the column's unique index says so. It counts
                // archived variants for the same reason.
                Rule::unique('product_variants', 'sku')->ignore($this->variant()->getKey()),
            ],
            'price_override' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999999999', 'decimal:0,6'],
            'is_active' => ['sometimes', 'boolean'],

            // Stock is deliberately not editable here. Every change to it has
            // to leave a kardex row saying who moved it and why, which is what
            // POST /variants/{variant}/adjust-stock does. Accepting a plain
            // number in this endpoint would be a way around that.
            'stock' => ['prohibited'],
            'reserved_quantity' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sku.required' => 'El SKU es obligatorio.',
            'sku.unique' => 'Ya existe una variante con ese SKU.',
            'price_override.decimal' => 'El precio admite hasta 6 decimales.',
            'stock.prohibited' => 'El stock se cambia con un ajuste de inventario, que exige un motivo.',
            'reserved_quantity.prohibited' => 'Las unidades reservadas las maneja el sistema de órdenes, no la edición de la variante.',
        ];
    }

    private function variant(): ProductVariant
    {
        return $this->route('variant');
    }
}
