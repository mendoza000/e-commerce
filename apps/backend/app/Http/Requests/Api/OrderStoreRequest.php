<?php

namespace App\Http\Requests\Api;

use App\Domain\Enums\DocumentType;
use App\Models\Municipality;
use App\Models\Parish;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class OrderStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer', 'distinct', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],

            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'regex:/^\+58\d{10}$/'],
            'document_type' => ['required', new Enum(DocumentType::class)],
            'document_number' => ['required', 'string', 'max:20'],

            'state_id' => ['required', 'integer', 'exists:states,id'],
            'municipality_id' => ['required', 'integer', 'exists:municipalities,id'],
            'parish_id' => ['required', 'integer', 'exists:parishes,id'],
            'address_reference' => ['required', 'string'],

            'payment_currency_id' => [
                'required',
                'integer',
                'exists:currencies,id',
                'exists:store_enabled_currencies,currency_id',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $stateId = $this->input('state_id');
            $municipalityId = $this->input('municipality_id');
            $parishId = $this->input('parish_id');

            if ($stateId && $municipalityId && ! $validator->errors()->has('municipality_id')) {
                $municipality = Municipality::query()->find($municipalityId);

                if ($municipality && (int) $municipality->state_id !== (int) $stateId) {
                    $validator->errors()->add(
                        'municipality_id',
                        'The selected municipality does not belong to the selected state.'
                    );
                }
            }

            if ($municipalityId && $parishId && ! $validator->errors()->has('parish_id')) {
                $parish = Parish::query()->find($parishId);

                if ($parish && (int) $parish->municipality_id !== (int) $municipalityId) {
                    $validator->errors()->add(
                        'parish_id',
                        'The selected parish does not belong to the selected municipality.'
                    );
                }
            }
        });
    }
}
