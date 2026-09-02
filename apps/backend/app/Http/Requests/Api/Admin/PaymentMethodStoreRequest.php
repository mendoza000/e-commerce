<?php

namespace App\Http\Requests\Api\Admin;

use App\Domain\Enums\PaymentMethodType;
use App\Models\StoreSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PaymentMethodStoreRequest extends FormRequest
{
    /**
     * Free-form beyond the type's own fields, but only this one extra key:
     * ManualPaymentProvider passes `notes` through to the customer, and any
     * other key would be stored and never shown to anyone.
     */
    private const EXTRA_INSTRUCTION_KEYS = ['notes'];

    public function authorize(): bool
    {
        // Authorised by the route group's `role:owner` middleware: payment
        // configuration is owner territory (docs/decisions.md).
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(PaymentMethodType::class)],
            'label' => ['required', 'string', 'max:255'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'instructions' => ['nullable', 'array'],
            // Values stay free-form but must be scalars: the providers read the
            // blob through instructionValue(), which casts to string.
            'instructions.*' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
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
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->assertCurrencyIsEnabled($validator);
                $this->assertInstructionKeysBelongToType($validator, $this->resolvedType());
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Debes elegir el tipo de método de pago.',
            'type.enum' => 'Ese tipo de método de pago no existe.',
            'label.required' => 'El nombre visible del método es obligatorio.',
            'currency_id.exists' => 'Esa moneda no existe.',
            'instructions.*.string' => 'Los datos de la cuenta deben ser texto.',
        ];
    }

    protected function resolvedType(): ?PaymentMethodType
    {
        return PaymentMethodType::tryFrom((string) $this->input('type'));
    }

    /**
     * A method charging in a currency the store does not accept is the same
     * inconsistency StoreSettingUpdateRequest refuses from the other side.
     */
    protected function assertCurrencyIsEnabled(Validator $validator): void
    {
        if (! $this->filled('currency_id')) {
            return;
        }

        $enabled = StoreSetting::current()->enabledCurrencies->pluck('id')->all();

        if (! in_array($this->integer('currency_id'), $enabled, true)) {
            $validator->errors()->add(
                'currency_id',
                'Esa moneda no está habilitada en la tienda. Habilítala en la configuración antes de cobrar en ella.',
            );
        }
    }

    /**
     * A key the type does not know about would be stored and then never read:
     * each provider only exposes the fields declared in
     * PaymentMethodType::instructionFields(). Better a 422 the admin can act
     * on than an account number that silently never reaches a customer.
     */
    protected function assertInstructionKeysBelongToType(Validator $validator, ?PaymentMethodType $type): void
    {
        if ($type === null || ! $this->has('instructions')) {
            return;
        }

        $allowed = [...$type->instructionFields(), ...self::EXTRA_INSTRUCTION_KEYS];
        $unknown = array_diff(array_keys((array) $this->input('instructions', [])), $allowed);

        if ($unknown === []) {
            return;
        }

        $validator->errors()->add(
            'instructions',
            'Estos datos no aplican a '.$type->label().': '.implode(', ', $unknown).
            '. Los campos válidos son: '.implode(', ', $allowed).'.',
        );
    }
}
