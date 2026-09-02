<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\ExchangeRateSettingStoreRequest;
use App\Http\Requests\Api\Admin\ExchangeRateSettingUpdateRequest;
use App\Http\Resources\Admin\ExchangeRateSettingResource;
use App\Models\ExchangeRate;
use App\Models\ExchangeRateSetting;
use App\Services\ExchangeRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * How each currency pair gets its rate: typed in by hand, or fetched from a
 * provider on a schedule.
 *
 * Deleting one stops the automation; it never touches the rates already
 * recorded for that pair, which stay in the history and keep pricing the
 * orders that used them.
 */
class ExchangeRateSettingController extends Controller
{
    public function index(ExchangeRateService $rates): AnonymousResourceCollection
    {
        $settings = ExchangeRateSetting::query()
            ->with(['fromCurrency', 'toCurrency'])
            ->orderBy('from_currency_id')
            ->orderBy('to_currency_id')
            ->get()
            ->each(fn (ExchangeRateSetting $setting) => $setting->setAttribute(
                'latest_rate',
                $this->latestRate($setting, $rates),
            ));

        return ExchangeRateSettingResource::collection($settings);
    }

    public function store(ExchangeRateSettingStoreRequest $request, ExchangeRateService $rates): JsonResponse
    {
        $setting = ExchangeRateSetting::create([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return ExchangeRateSettingResource::make($this->detail($setting, $rates))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        ExchangeRateSettingUpdateRequest $request,
        ExchangeRateSetting $rateSetting,
        ExchangeRateService $rates,
    ): JsonResponse {
        $rateSetting->update($request->validated());

        return ExchangeRateSettingResource::make($this->detail($rateSetting->fresh(), $rates))->response();
    }

    public function destroy(ExchangeRateSetting $rateSetting): JsonResponse
    {
        $rateSetting->delete();

        return response()->json(status: 204);
    }

    private function detail(ExchangeRateSetting $setting, ExchangeRateService $rates): ExchangeRateSetting
    {
        $setting->load(['fromCurrency', 'toCurrency']);
        $setting->setAttribute('latest_rate', $this->latestRate($setting, $rates));

        return $setting;
    }

    private function latestRate(ExchangeRateSetting $setting, ExchangeRateService $rates): ?ExchangeRate
    {
        return $rates->latestRate($setting->fromCurrency, $setting->toCurrency);
    }
}
