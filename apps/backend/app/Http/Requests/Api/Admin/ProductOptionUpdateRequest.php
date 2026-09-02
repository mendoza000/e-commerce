<?php

namespace App\Http\Requests\Api\Admin;

use App\Models\ProductOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductOptionUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Renaming an option is always allowed, even with variants built on it:
     * the name is a label, and no variant identity depends on it. Only adding
     * or removing options changes what a variant means.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $option = $this->option();

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('product_options', 'name')
                    ->where('product_id', $option->product_id)
                    ->ignore($option->getKey()),
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
            'name.unique' => 'Este producto ya tiene una opción con ese nombre.',
        ];
    }

    private function option(): ProductOption
    {
        return $this->route('option');
    }
}
