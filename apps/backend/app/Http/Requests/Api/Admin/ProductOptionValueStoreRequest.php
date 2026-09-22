<?php

namespace App\Http\Requests\Api\Admin;

use App\Models\ProductOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductOptionValueStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Adding a value is always allowed, unlike adding an option: existing
     * variants stay perfectly well defined, and the generator picks up the new
     * combinations on its next run.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'value' => [
                'required',
                'string',
                'max:255',
                Rule::unique('product_option_values', 'value')
                    ->where('product_option_id', $this->option()->getKey()),
            ],
            'position' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'value.required' => 'El valor es obligatorio.',
            'value.unique' => 'Esta opción ya tiene ese valor.',
        ];
    }

    private function option(): ProductOption
    {
        return $this->route('option');
    }
}
