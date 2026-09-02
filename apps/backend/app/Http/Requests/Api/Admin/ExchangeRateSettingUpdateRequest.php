<?php

namespace App\Http\Requests\Api\Admin;

use App\Domain\Enums\ExchangeRateMode;
use App\Domain\Enums\ExchangeRateProviderType;
use App\Models\ExchangeRateSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The pair itself is not editable: changing which currencies a configuration
 * is about turns it into a different configuration, and its refresh history
 * (`last_run_at`, `last_error`) would then describe a pair it never ran for.
 * Delete it and create the other one.
 */
class ExchangeRateSettingUpdateRequest extends FormRequest
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
            'mode' => ['sometimes', 'required', Rule::enum(ExchangeRateMode::class)],
            'provider' => ['sometimes', 'nullable', Rule::enum(ExchangeRateProviderType::class)],
            'frequency_minutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10080'],
            'reference_amount' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'max:999999999999', 'decimal:0,6'],
            'is_active' => ['sometimes', 'boolean'],

            'from_currency_id' => ['prohibited'],
            'to_currency_id' => ['prohibited'],
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

                $setting = $this->setting();

                // Read from the request when it says so, from the stored row
                // when it does not: switching to automatic without naming a
                // provider has to be caught even though `provider` is absent.
                $mode = $this->has('mode') ? $this->input('mode') : $setting->mode->value;
                $provider = $this->has('provider') ? $this->input('provider') : $setting->provider;

                if ($mode !== ExchangeRateMode::Automatic->value) {
                    return;
                }

                if ($provider === null || $provider === ExchangeRateProviderType::Manual->value) {
                    $validator->errors()->add(
                        'provider',
                        'Un par automático necesita una fuente automática: elige una distinta de "manual".',
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mode.enum' => 'El modo solo puede ser manual o automático.',
            'provider.enum' => 'Esa fuente de tasas no existe.',
            'from_currency_id.prohibited' => 'El par de monedas no se edita: elimina esta configuración y crea la otra.',
            'to_currency_id.prohibited' => 'El par de monedas no se edita: elimina esta configuración y crea la otra.',
        ];
    }

    private function setting(): ExchangeRateSetting
    {
        return $this->route('rateSetting');
    }
}
