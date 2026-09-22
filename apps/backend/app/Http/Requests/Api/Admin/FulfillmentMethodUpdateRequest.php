<?php

namespace App\Http\Requests\Api\Admin;

use App\Models\FulfillmentMethod;
use Illuminate\Validation\Validator;

/**
 * The type is not editable: it decides which provider prices the method and
 * whether a tracking code is meaningful for it. Changing it in place would
 * reinterpret an existing zone-rate table as belonging to another kind of
 * shipping entirely. Create the other method and deactivate this one — same
 * rule as PaymentMethodUpdateRequest.
 */
class FulfillmentMethodUpdateRequest extends FulfillmentMethodStoreRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['sometimes', 'required', 'string', 'max:255'],
            'requires_tracking_code' => ['sometimes', 'boolean'],
            'base_cost' => ['sometimes', 'nullable', 'numeric', 'gte:0', 'max:999999999999', 'decimal:0,6'],
            'currency_id' => ['sometimes', 'nullable', 'integer', 'exists:currencies,id'],
            'is_active' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer', 'min:0', 'max:1000'],

            'type' => ['prohibited'],
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

                $this->assertCostHasCurrency($validator);
                $this->assertCurrencyIsEnabled($validator);
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'type.prohibited' => 'El tipo de un método de envío no se cambia: crea otro método y desactiva este.',
        ];
    }

    /**
     * Falls back to the stored row so a request that only touches `base_cost`
     * is not misread as clearing the currency it already has.
     */
    protected function resolvedCurrencyId(): ?int
    {
        return $this->fulfillmentMethod()->currency_id;
    }

    private function fulfillmentMethod(): FulfillmentMethod
    {
        return $this->route('fulfillmentMethod');
    }
}
