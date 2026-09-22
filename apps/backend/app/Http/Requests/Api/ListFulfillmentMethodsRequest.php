<?php

namespace App\Http\Requests\Api;

use App\Models\Municipality;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Both filters are optional: a storefront that has not collected an address
 * yet (PRD section 6, "si aplica más de una opción") still needs to list the
 * available methods, just without a priced estimate.
 */
class ListFulfillmentMethodsRequest extends FormRequest
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
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'municipality_id' => ['nullable', 'integer', 'exists:municipalities,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $stateId = $this->input('state_id');
            $municipalityId = $this->input('municipality_id');

            if ($stateId === null || $municipalityId === null || $validator->errors()->has('municipality_id')) {
                return;
            }

            $municipality = Municipality::query()->find($municipalityId);

            if ($municipality !== null && (int) $municipality->state_id !== (int) $stateId) {
                $validator->errors()->add('municipality_id', 'Ese municipio no pertenece al estado indicado.');
            }
        });
    }
}
