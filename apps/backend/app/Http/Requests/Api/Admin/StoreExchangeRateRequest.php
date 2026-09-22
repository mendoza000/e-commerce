<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A rate the admin types in. Registering one never edits the previous row —
 * see ExchangeRateService::storeManual().
 */
class StoreExchangeRateRequest extends FormRequest
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
            'from_currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'to_currency_id' => ['required', 'integer', 'exists:currencies,id', 'different:from_currency_id'],
            // decimal(18,6), and strictly above zero: a rate of 0 would price
            // every order in that currency at nothing.
            'rate' => ['required', 'numeric', 'gt:0', 'max:999999999999', 'decimal:0,6'],
            // The volume the quote is for, when the source is volume-sensitive
            // (a P2P order book is). Optional, and only informative on a rate
            // the admin typed by hand.
            'reference_amount' => ['nullable', 'numeric', 'gt:0', 'max:999999999999', 'decimal:0,6'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from_currency_id.required' => 'Debes indicar la moneda de origen.',
            'to_currency_id.required' => 'Debes indicar la moneda de destino.',
            'to_currency_id.different' => 'Una moneda no se cambia por sí misma.',
            'rate.required' => 'Debes indicar la tasa.',
            'rate.gt' => 'La tasa tiene que ser mayor que cero.',
            'rate.decimal' => 'La tasa admite hasta 6 decimales.',
        ];
    }
}
