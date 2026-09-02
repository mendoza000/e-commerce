<?php

namespace App\Models;

use App\Domain\Enums\ExchangeRateMode;
use App\Domain\ExchangeRates\Contracts\ExchangeRateProviderInterface;
use App\Domain\ExchangeRates\ExchangeRateProviderRegistry;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'from_currency_id',
    'to_currency_id',
    'mode',
    'provider',
    'frequency_minutes',
    'reference_amount',
    'is_active',
    'last_run_at',
    'last_error_at',
    'last_error',
])]
class ExchangeRateSetting extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'mode' => ExchangeRateMode::class,
            'reference_amount' => 'decimal:6',
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
            'last_error_at' => 'datetime',
        ];
    }

    public function fromCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'from_currency_id');
    }

    public function toCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'to_currency_id');
    }

    /**
     * Pairs the scheduled refresh should even look at. Manual pairs are
     * excluded here so the job can never overwrite a rate the admin typed in.
     */
    #[Scope]
    protected function automatic(Builder $query): void
    {
        $query->where('is_active', true)->where('mode', ExchangeRateMode::Automatic);
    }

    /**
     * Deliberately not named provider(): `provider` is also a column on this
     * table, and having both read the same at a glance would be confusing.
     */
    public function rateProvider(): ExchangeRateProviderInterface
    {
        return app(ExchangeRateProviderRegistry::class)->for($this->provider);
    }

    /**
     * Whether enough time has passed since the last attempt — successful or
     * not — for this pair to be fetched again.
     *
     * A pair with no frequency configured is refreshed on every run, which is
     * the safe default: the scheduler's own cadence becomes the frequency.
     */
    public function isDueForRefresh(): bool
    {
        if (! $this->is_active || $this->mode !== ExchangeRateMode::Automatic) {
            return false;
        }

        if ($this->last_run_at === null || $this->frequency_minutes === null) {
            return true;
        }

        return $this->last_run_at->addMinutes($this->frequency_minutes)->isPast();
    }

    /**
     * Records a successful fetch. `last_run_at` moves on both outcomes so a
     * failing source cannot be retried on every single scheduler tick.
     */
    public function markRefreshed(): void
    {
        $this->update([
            'last_run_at' => now(),
            'last_error_at' => null,
            'last_error' => null,
        ]);
    }

    public function markFailed(string $message): void
    {
        $this->update([
            'last_run_at' => now(),
            'last_error_at' => now(),
            'last_error' => $message,
        ]);
    }

    /**
     * Human-readable pair, e.g. "USD/VES" — used in logs and admin listings.
     */
    public function pairLabel(): string
    {
        return $this->fromCurrency->code.'/'.$this->toCurrency->code;
    }

    /**
     * How this pair is doing, for the panel to show at a glance.
     *
     * A failing source is invisible otherwise: a failed refresh writes nothing
     * to `exchange_rates`, so the store keeps quoting the last good rate and
     * nobody notices until it is badly stale (PRD 8bis). This is what turns
     * `last_error_at` into something an admin actually sees.
     */
    public function healthStatus(): string
    {
        if (! $this->is_active) {
            return 'inactive';
        }

        if ($this->mode !== ExchangeRateMode::Automatic) {
            return 'manual';
        }

        if ($this->last_error_at !== null) {
            return 'failing';
        }

        return $this->last_run_at === null ? 'pending' : 'ok';
    }
}
