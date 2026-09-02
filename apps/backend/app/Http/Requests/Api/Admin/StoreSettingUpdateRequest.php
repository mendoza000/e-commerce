<?php

namespace App\Http\Requests\Api\Admin;

use App\Models\Currency;
use App\Models\StoreSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The store's own configuration. Two invariants are enforced here rather than
 * in the controller, because both are about the request as a whole and neither
 * survives being checked field by field.
 */
class StoreSettingUpdateRequest extends FormRequest
{
    private ?StoreSetting $settings = null;

    public function authorize(): bool
    {
        // Authorised by the route group's `role:owner` middleware: store
        // configuration is owner territory (docs/decisions.md).
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'store_name' => ['sometimes', 'required', 'string', 'max:255'],
            // The column is char(7): a hex colour with its leading hash.
            'primary_color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'secondary_color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            // Stored as the customer would dial it. WhatsApp links are built
            // from this, so it is kept as digits with an optional leading +.
            'whatsapp_number' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\+?[0-9]{7,15}$/'],
            'base_currency_id' => ['sometimes', 'required', 'integer', 'exists:currencies,id'],
            'enabled_currencies' => ['sometimes', 'required', 'array', 'min:1'],
            'enabled_currencies.*' => ['integer', 'exists:currencies,id'],
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

                $this->assertBaseCurrencyIsEnabled($validator);
                $this->assertNoActivePaymentMethodIsStranded($validator);
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'store_name.required' => 'El nombre de la tienda es obligatorio.',
            'primary_color.regex' => 'El color debe ser hexadecimal, por ejemplo #1a2b3c.',
            'secondary_color.regex' => 'El color debe ser hexadecimal, por ejemplo #1a2b3c.',
            'whatsapp_number.regex' => 'El número de WhatsApp solo puede tener dígitos, con un + opcional al inicio.',
            'base_currency_id.exists' => 'Esa moneda no existe.',
            'enabled_currencies.min' => 'La tienda tiene que aceptar al menos una moneda.',
        ];
    }

    /**
     * Every price in the store is expressed in the base currency, so a base
     * currency the store does not accept would leave the storefront quoting in
     * something it refuses to be paid in.
     */
    private function assertBaseCurrencyIsEnabled(Validator $validator): void
    {
        $base = (int) ($this->input('base_currency_id') ?? $this->settings()->base_currency_id);

        if (! in_array($base, $this->resolvedEnabledIds(), true)) {
            $validator->errors()->add(
                'enabled_currencies',
                'La moneda base tiene que estar entre las monedas habilitadas.',
            );
        }
    }

    /**
     * Disabling a currency an active payment method charges in would leave the
     * storefront offering a way to pay in something the store says it does not
     * accept. Deactivating that method first is the admin's decision to make.
     */
    private function assertNoActivePaymentMethodIsStranded(Validator $validator): void
    {
        if (! $this->has('enabled_currencies')) {
            return;
        }

        $removed = array_diff(
            $this->settings()->enabledCurrencies->pluck('id')->all(),
            $this->resolvedEnabledIds(),
        );

        foreach ($removed as $currencyId) {
            $currency = Currency::query()->find($currencyId);

            if ($currency?->isUsedByActivePaymentMethod()) {
                $validator->errors()->add(
                    'enabled_currencies',
                    "No puedes deshabilitar {$currency->code}: hay métodos de pago activos que cobran en esa moneda.",
                );
            }
        }
    }

    /**
     * The set of enabled currencies this request would leave behind — what it
     * sends, or what is already stored when it does not mention them.
     *
     * @return array<int, int>
     */
    private function resolvedEnabledIds(): array
    {
        if (! $this->has('enabled_currencies')) {
            return $this->settings()->enabledCurrencies->pluck('id')->all();
        }

        return array_map('intval', (array) $this->input('enabled_currencies'));
    }

    /**
     * The single store row this request is about. Memoised because both
     * checks above compare against what is currently stored.
     */
    private function settings(): StoreSetting
    {
        return $this->settings ??= StoreSetting::current();
    }
}
