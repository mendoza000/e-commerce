<?php

namespace App\Http\Requests\Api\Admin;

use App\Domain\Enums\FulfillmentMethodType;
use App\Models\StoreSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class FulfillmentMethodStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorised by the route group's `role:owner` middleware: shipping
        // configuration is owner territory, same as payment methods.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(FulfillmentMethodType::class)],
            'label' => ['required', 'string', 'max:255'],
            'requires_tracking_code' => ['sometimes', 'boolean'],
            'base_cost' => ['nullable', 'numeric', 'gte:0', 'max:999999999999', 'decimal:0,6'],
            'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
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
            'type.required' => 'Debes elegir el tipo de método de envío.',
            'type.enum' => 'Ese tipo de método de envío no existe.',
            'label.required' => 'El nombre visible del método es obligatorio.',
            'currency_id.exists' => 'Esa moneda no existe.',
        ];
    }

    /**
     * A flat cost with no currency is a number nobody can charge: it would
     * show up on a checkout screen with no unit attached. On create there is
     * no stored row to fall back on, so an omitted currency_id is simply
     * absent.
     */
    protected function assertCostHasCurrency(Validator $validator): void
    {
        $hasCost = $this->filled('base_cost');
        $hasCurrency = $this->filled('currency_id') || $this->resolvedCurrencyId() !== null;

        if ($hasCost && ! $hasCurrency) {
            $validator->errors()->add(
                'currency_id',
                'Un método con costo base necesita una moneda.',
            );
        }
    }

    /**
     * Same inconsistency StoreSettingUpdateRequest and PaymentMethodStoreRequest
     * already refuse from their own sides: quoting shipping in a currency the
     * store does not accept.
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
     * On create there is no stored row, so a currency not sent in the request
     * simply is not set. FulfillmentMethodUpdateRequest overrides this to fall
     * back on the method being edited, so a partial update of `base_cost`
     * alone does not misread an already-configured currency as missing.
     */
    protected function resolvedCurrencyId(): ?int
    {
        return null;
    }
}
