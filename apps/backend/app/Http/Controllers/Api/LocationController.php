<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListMunicipalitiesRequest;
use App\Http\Requests\Api\ListParishesRequest;
use App\Http\Resources\MunicipalityResource;
use App\Http\Resources\ParishResource;
use App\Http\Resources\StateResource;
use App\Models\Municipality;
use App\Models\Parish;
use App\Models\State;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LocationController extends Controller
{
    public function states(): AnonymousResourceCollection
    {
        return StateResource::collection(State::query()->orderBy('name')->get());
    }

    public function municipalities(ListMunicipalitiesRequest $request): AnonymousResourceCollection
    {
        $municipalities = Municipality::query()
            ->where('state_id', $request->integer('state_id'))
            ->orderBy('name')
            ->get();

        return MunicipalityResource::collection($municipalities);
    }

    public function parishes(ListParishesRequest $request): AnonymousResourceCollection
    {
        $parishes = Parish::query()
            ->where('municipality_id', $request->integer('municipality_id'))
            ->orderBy('name')
            ->get();

        return ParishResource::collection($parishes);
    }
}
