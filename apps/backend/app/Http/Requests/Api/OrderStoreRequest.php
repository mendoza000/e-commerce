<?php

namespace App\Http\Requests\Api;

use App\Domain\Enums\DocumentType;
use App\Models\Municipality;
use App\Models\Parish;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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

            // The payment currency is NOT accepted from the client: it is
            // whatever the chosen method charges in (PaymentMethod::currency),
            // so an order can never be frozen in one currency and paid in
            // another. See OrderService::createOrder.
            'payment_method_id' => [
                'required',
                'integer',
                Rule::exists('payment_methods', 'id')->where('is_active', true),
            ],

            // Optional, unlike payment_method_id: PRD section 6 only asks for a
            // selector "si aplica más de una opción" — a store with one method,
            // or none configured yet, still has to be able to check out.
            'fulfillment_method_id' => [
                'nullable',
                'integer',
                Rule::exists('fulfillment_methods', 'id')->where('is_active', true),
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
