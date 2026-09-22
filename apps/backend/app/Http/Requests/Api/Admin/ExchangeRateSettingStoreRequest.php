<?php

namespace App\Http\Requests\Api\Admin;

use App\Domain\Enums\ExchangeRateMode;
use App\Domain\Enums\ExchangeRateProviderType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ExchangeRateSettingStoreRequest extends FormRequest
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
            'from_currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'to_currency_id' => [
                'required',
                'integer',
                'exists:currencies,id',
                'different:from_currency_id',
                // One configuration per pair: the column pair is unique, and
                // two rows would mean two schedules fighting over one rate.
                Rule::unique('exchange_rate_settings', 'to_currency_id')
                    ->where('from_currency_id', $this->integer('from_currency_id')),
            ],
            'mode' => ['required', Rule::enum(ExchangeRateMode::class)],
            // Validated against the registry's own enum rather than a literal
            // list, so a provider that does not exist can never be configured.
            'provider' => ['nullable', Rule::enum(ExchangeRateProviderType::class)],
            'frequency_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'reference_amount' => ['nullable', 'numeric', 'gt:0', 'max:999999999999', 'decimal:0,6'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->assertProviderMatchesMode($validator)];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to_currency_id.unique' => 'Ya existe una configuración para ese par de monedas.',
            'to_currency_id.different' => 'Una moneda no se cambia por sí misma.',
            'mode.enum' => 'El modo solo puede ser manual o automático.',
            'provider.enum' => 'Esa fuente de tasas no existe.',
            'frequency_minutes.max' => 'La frecuencia máxima es de una semana (10080 minutos).',
        ];
    }

    /**
     * An automatic pair pointed at the manual provider would be a schedule
     * with nothing to call: the refresh command skips it silently and the pair
     * looks configured while never updating.
     */
    protected function assertProviderMatchesMode(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $mode = $this->input('mode');
        $provider = $this->input('provider');

        if ($mode !== ExchangeRateMode::Automatic->value) {
            return;
        }

        if ($provider === null || $provider === ExchangeRateProviderType::Manual->value) {
            $validator->errors()->add(
                'provider',
                'Un par automático necesita una fuente automática: elige una distinta de "manual".',
            );
        }
    }
}
