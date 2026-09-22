<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A manual stock correction. The reason is required, not optional: this is the
 * only movement type in the kardex that no automatic path produces, so an
 * unexplained one is unexplainable forever.
 */
class AdjustStockRequest extends FormRequest
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
            // Signed and relative: "+12 llegaron" / "-3 rotas". An absolute
            // target would silently swallow a sale confirmed a second earlier.
            'quantity_change' => ['required', 'integer', 'not_in:0', 'min:-1000000', 'max:1000000'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity_change.required' => 'Debes indicar cuántas unidades entran o salen.',
            'quantity_change.integer' => 'El ajuste debe ser un número entero de unidades.',
            'quantity_change.not_in' => 'Un ajuste de 0 unidades no cambia nada.',
            'reason.required' => 'Debes indicar el motivo del ajuste.',
            'reason.min' => 'El motivo es demasiado corto.',
        ];
    }
}
