<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\ListExchangeRatesRequest;
use App\Http\Requests\Api\Admin\StoreExchangeRateRequest;
use App\Http\Resources\Admin\ExchangeRateResource;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\User;
use App\Services\ExchangeRateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The rate history, and the one way to add to it by hand.
 *
 * There is no update and no delete on purpose. `exchange_rates` is the record
 * an order's frozen rate is justified against: rewriting a past row would make
 * an order that was correct at the time look wrong, and deleting one would
 * leave it unexplainable. Correcting a rate means registering the right one
 * now — the newest `effective_at` is what the storefront quotes.
 */
class ExchangeRateController extends Controller
{
    public function index(ListExchangeRatesRequest $request): AnonymousResourceCollection
    {
        $rates = ExchangeRate::query()
            ->with(['fromCurrency', 'toCurrency', 'creator'])
            ->when(
                $request->filled('from_currency_id'),
                fn (Builder $query) => $query->where('from_currency_id', $request->integer('from_currency_id')),
            )
            ->when(
                $request->filled('to_currency_id'),
                fn (Builder $query) => $query->where('to_currency_id', $request->integer('to_currency_id')),
            )
            ->when(
                $request->filled('source'),
                fn (Builder $query) => $query->where('source', (string) $request->string('source')),
            )
            // Newest first, with the id as tiebreaker: a manual rate and a
            // scheduled one can land in the same second.
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return ExchangeRateResource::collection($rates);
    }

    public function store(StoreExchangeRateRequest $request, ExchangeRateService $rates): JsonResponse
    {
        $rate = $rates->storeManual(
            Currency::query()->findOrFail($request->integer('from_currency_id')),
            Currency::query()->findOrFail($request->integer('to_currency_id')),
            (string) $request->string('rate'),
            $request->filled('reference_amount') ? (string) $request->string('reference_amount') : null,
            $this->admin($request),
        );

        return ExchangeRateResource::make($rate->load(['fromCurrency', 'toCurrency', 'creator']))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * The route group guarantees an authenticated, active owner; this only
     * narrows the type for the service, which records who set the rate.
     */
    private function admin(Request $request): User
    {
        return $request->user();
    }
}
