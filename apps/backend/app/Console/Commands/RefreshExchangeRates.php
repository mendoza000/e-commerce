<?php

namespace App\Console\Commands;

use App\Models\ExchangeRateSetting;
use App\Services\ExchangeRateService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('exchange-rates:refresh')]
#[Description('Fetches the configured automatic currency pairs and stores their current rate.')]
class RefreshExchangeRates extends Command
{
    /**
     * The only place in the application that calls an external rate API.
     * A pair that fails is recorded and skipped — never fatal, because the
     * checkout keeps using the last stored rate (PRD 8bis).
     */
    public function handle(ExchangeRateService $rates): int
    {
        $settings = ExchangeRateSetting::query()
            ->automatic()
            ->with(['fromCurrency', 'toCurrency'])
            ->get()
            ->filter(fn (ExchangeRateSetting $setting) => $setting->isDueForRefresh());

        $updated = 0;
        $failed = 0;

        foreach ($settings as $setting) {
            if ($rates->refresh($setting) !== null) {
                $updated++;

                $this->info("{$setting->pairLabel()}: actualizada.");

                continue;
            }

            $failed++;

            $this->warn("{$setting->pairLabel()}: sin actualizar ({$setting->fresh()->last_error}).");
        }

        $this->info("Refreshed {$updated} rate(s), {$failed} failed.");

        return self::SUCCESS;
    }
}
