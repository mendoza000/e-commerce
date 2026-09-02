<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The order the storefront lists shipping methods in. Sent whole, like
 * ReorderPaymentMethodsRequest: a partial list would leave the methods it
 * omits sharing positions with the ones it moved.
 */
class ReorderFulfillmentMethodsRequest extends FormRequest
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
            'fulfillment_methods' => ['required', 'array', 'min:1'],
            'fulfillment_methods.*' => ['integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fulfillment_methods.required' => 'Debes enviar el orden de los métodos de envío.',
        ];
    }
}
