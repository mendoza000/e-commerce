<?php

namespace App\Providers;

use App\Domain\ExchangeRates\ExchangeRateProviderRegistry;
use App\Domain\Payments\PaymentProviderRegistry;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Both registries are stateless lookups, so one shared instance is
        // enough. Models resolve them through the container
        // (PaymentMethod::provider, ExchangeRateSetting::rateProvider), which
        // keeps the mapping swappable in tests.
        $this->app->singleton(PaymentProviderRegistry::class);
        $this->app->singleton(ExchangeRateProviderRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
