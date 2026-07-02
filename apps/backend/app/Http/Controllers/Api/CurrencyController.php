<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CurrencyResource;
use App\Models\StoreSetting;
use App\Services\ExchangeRateService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CurrencyController extends Controller
{
    public function index(ExchangeRateService $rates): AnonymousResourceCollection
    {
        $store = StoreSetting::current();

        return CurrencyResource::collection($rates->enabledCurrenciesWithRates($store));
    }
}
