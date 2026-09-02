<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The order the storefront lists payment methods in. Sent whole, like the
 * product image reorder: a partial list would leave the methods it omits
 * sharing positions with the ones it moved.
 */
class ReorderPaymentMethodsRequest extends FormRequest
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
            'payment_methods' => ['required', 'array', 'min:1'],
            'payment_methods.*' => ['integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_methods.required' => 'Debes enviar el orden de los métodos de pago.',
        ];
    }
}
