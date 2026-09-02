<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListFulfillmentMethodsRequest;
use App\Http\Resources\FulfillmentMethodResource;
use App\Models\FulfillmentMethod;
use App\Models\Municipality;
use App\Models\State;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FulfillmentMethodController extends Controller
{
    public function index(ListFulfillmentMethodsRequest $request): AnonymousResourceCollection
    {
        $state = $request->filled('state_id') ? State::query()->find($request->integer('state_id')) : null;
        $municipality = $request->filled('municipality_id')
            ? Municipality::query()->find($request->integer('municipality_id'))
            : null;

        $methods = FulfillmentMethod::query()->active()->with('currency')->get();

        // Priced only when a state was given: the frontend calls this before
        // an address exists (to list the options) and again after (to price
        // them), and both are the same endpoint.
        $methods->each(fn (FulfillmentMethod $method) => $method->setAttribute(
            'estimated_cost',
            $state !== null ? $method->estimateCostFor($state, $municipality) : null,
        ));

        return FulfillmentMethodResource::collection($methods);
    }
}
