<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\FulfillmentZoneRateStoreRequest;
use App\Http\Requests\Api\Admin\FulfillmentZoneRateUpdateRequest;
use App\Http\Resources\Admin\FulfillmentZoneRateResource;
use App\Models\FulfillmentMethod;
use App\Models\FulfillmentZoneRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Per-zone shipping overrides for a fulfillment method (PRD section 6: "tarifa
 * plana por zona, o a coordinar"). A method with no rows here still has a
 * price — FulfillmentMethod::estimateCostFor() falls back to `base_cost`, and
 * to null ("a coordinar") when that is unset too.
 */
class FulfillmentZoneRateController extends Controller
{
    public function index(FulfillmentMethod $fulfillmentMethod): AnonymousResourceCollection
    {
        $rates = $fulfillmentMethod->zoneRates()
            ->with(['state', 'municipality'])
            ->orderBy('state_id')
            ->orderByRaw('municipality_id IS NULL DESC')
            ->orderBy('municipality_id')
            ->get();

        return FulfillmentZoneRateResource::collection($rates);
    }

    public function store(FulfillmentZoneRateStoreRequest $request, FulfillmentMethod $fulfillmentMethod): JsonResponse
    {
        $rate = $fulfillmentMethod->zoneRates()->create($request->validated());

        return FulfillmentZoneRateResource::make($this->detail($rate))
            ->response()
            ->setStatusCode(201);
    }

    public function update(FulfillmentZoneRateUpdateRequest $request, FulfillmentZoneRate $zoneRate): JsonResponse
    {
        $zoneRate->update($request->validated());

        return FulfillmentZoneRateResource::make($this->detail($zoneRate->fresh()))->response();
    }

    public function destroy(FulfillmentZoneRate $zoneRate): JsonResponse
    {
        $zoneRate->delete();

        return response()->json(status: 204);
    }

    private function detail(FulfillmentZoneRate $rate): FulfillmentZoneRate
    {
        return $rate->load(['state', 'municipality']);
    }
}
