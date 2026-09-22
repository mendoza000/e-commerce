<?php

namespace App\Http\Requests\Api\Admin;

use App\Domain\Enums\PaymentMethodType;
use App\Models\PaymentMethod;
use Illuminate\Validation\Validator;

/**
 * The type is not editable: it decides which provider serves the method, which
 * account fields it has and whether it needs a proof. Changing it in place
 * would reinterpret the stored `instructions` as another method's fields, and
 * the orders already placed with it would describe an account nobody ever
 * published. Create the other method and deactivate this one.
 */
class PaymentMethodUpdateRequest extends PaymentMethodStoreRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['sometimes', 'required', 'string', 'max:255'],
            'currency_id' => ['sometimes', 'required', 'integer', 'exists:currencies,id'],
            // Replaces the blob wholesale rather than merging: a merge would
            // make an emptied field indistinguishable from an absent one, and
            // clearing a stale account number has to be possible.
            'instructions' => ['sometimes', 'nullable', 'array'],
            'instructions.*' => ['nullable', 'string', 'max:255'],
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

                $this->assertCurrencyIsEnabled($validator);
                $this->assertInstructionKeysBelongToType($validator, $this->resolvedType());
            },
        ];
    }

    /**
     * The type comes from the stored row, never from the request — the request
     * is not allowed to carry one.
     */
    protected function resolvedType(): ?PaymentMethodType
    {
        return $this->paymentMethod()->type;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'type.prohibited' => 'El tipo de un método de pago no se cambia: crea otro método y desactiva este.',
        ];
    }

    private function paymentMethod(): PaymentMethod
    {
        return $this->route('paymentMethod');
    }
}
