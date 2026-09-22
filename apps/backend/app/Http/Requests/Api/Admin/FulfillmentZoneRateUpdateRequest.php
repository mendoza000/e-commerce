<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The zone itself is not editable: state/municipality say which destination
 * this rate describes, and changing them would turn one zone's rate into
 * another zone's, keyed under an id that used to mean something else — same
 * "the pair is not editable" rule as ExchangeRateSettingUpdateRequest. Delete
 * this rate and create the other one.
 */
class FulfillmentZoneRateUpdateRequest extends FormRequest
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
            // Null means "a coordinar" for this zone.
            'cost' => ['sometimes', 'nullable', 'numeric', 'gte:0', 'max:999999999999', 'decimal:0,6'],

            'state_id' => ['prohibited'],
            'municipality_id' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'state_id.prohibited' => 'La zona de una tarifa no se edita: elimínala y crea la otra.',
            'municipality_id.prohibited' => 'La zona de una tarifa no se edita: elimínala y crea la otra.',
        ];
    }
}
