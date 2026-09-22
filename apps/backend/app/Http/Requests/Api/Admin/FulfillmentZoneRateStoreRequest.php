<?php

namespace App\Http\Requests\Api\Admin;

use App\Models\FulfillmentMethod;
use App\Models\FulfillmentZoneRate;
use App\Models\Municipality;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class FulfillmentZoneRateStoreRequest extends FormRequest
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
            'state_id' => ['required', 'integer', 'exists:states,id'],
            // Null means the rate applies to the whole state.
            'municipality_id' => ['nullable', 'integer', 'exists:municipalities,id'],
            // Null means "a coordinar" for this exact zone — a deliberate
            // override, distinct from no row existing at all.
            'cost' => ['nullable', 'numeric', 'gte:0', 'max:999999999999', 'decimal:0,6'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->assertMunicipalityBelongsToState($validator);
                $this->assertZoneNotAlreadyConfigured($validator);
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'state_id.required' => 'Debes elegir el estado de la zona.',
        ];
    }

    protected function assertMunicipalityBelongsToState(Validator $validator): void
    {
        $municipalityId = $this->input('municipality_id');

        if ($municipalityId === null) {
            return;
        }

        $municipality = Municipality::query()->find($municipalityId);

        if ($municipality !== null && (int) $municipality->state_id !== $this->integer('state_id')) {
            $validator->errors()->add('municipality_id', 'Ese municipio no pertenece al estado seleccionado.');
        }
    }

    protected function assertZoneNotAlreadyConfigured(Validator $validator): void
    {
        $query = FulfillmentZoneRate::query()
            ->where('fulfillment_method_id', $this->fulfillmentMethod()->id)
            ->where('state_id', $this->integer('state_id'));

        $municipalityId = $this->input('municipality_id');

        $municipalityId === null
            ? $query->whereNull('municipality_id')
            : $query->where('municipality_id', $municipalityId);

        if ($query->exists()) {
            $validator->errors()->add(
                'state_id',
                'Ya existe una tarifa para esa zona en este método de envío. Edítala en vez de crear otra.',
            );
        }
    }

    protected function fulfillmentMethod(): FulfillmentMethod
    {
        return $this->route('fulfillmentMethod');
    }
}
