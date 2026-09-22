<?php

namespace App\Http\Requests\Api\Admin;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProductOptionStoreRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:255',
                // "Color" twice on one product would make every generated
                // combination ambiguous.
                Rule::unique('product_options', 'name')
                    ->where('product_id', $this->product()->getKey()),
            ],
            'position' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                // Adding an option to a product whose variants are already
                // built on other options would leave those variants silently
                // undefined on the new one — a "Rojo-M" that says nothing
                // about Material. The variants have to go first, and that is
                // the admin's call, not a cascade's.
                $hasCombinations = $this->product()
                    ->variants()
                    ->whereHas('optionValues')
                    ->exists();

                if ($hasCombinations) {
                    $validator->errors()->add(
                        'name',
                        'Este producto ya tiene variantes con opciones. Elimínalas antes de agregar otra opción.',
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la opción es obligatorio.',
            'name.unique' => 'Este producto ya tiene una opción con ese nombre.',
        ];
    }

    private function product(): Product
    {
        return $this->route('product');
    }
}
