<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ListExchangeRatesRequest extends FormRequest
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
            'from_currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'to_currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'source' => ['nullable', 'string', 'max:50'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from_currency_id.exists' => 'Esa moneda no existe.',
            'to_currency_id.exists' => 'Esa moneda no existe.',
        ];
    }
}
