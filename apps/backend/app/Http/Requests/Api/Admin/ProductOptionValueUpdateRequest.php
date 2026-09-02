<?php

namespace App\Http\Requests\Api\Admin;

use App\Models\ProductOptionValue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductOptionValueUpdateRequest extends FormRequest
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
        $value = $this->optionValue();

        return [
            'value' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('product_option_values', 'value')
                    ->where('product_option_id', $value->product_option_id)
                    ->ignore($value->getKey()),
            ],
            'position' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'value.unique' => 'Esta opción ya tiene ese valor.',
        ];
    }

    private function optionValue(): ProductOptionValue
    {
        return $this->route('optionValue');
    }
}
