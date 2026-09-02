<?php

namespace App\Http\Resources\Admin;

use App\Models\FulfillmentZoneRate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FulfillmentZoneRate
 */
class FulfillmentZoneRateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'state' => $this->whenLoaded('state', fn () => [
                'id' => $this->state->id,
                'name' => $this->state->name,
            ]),
            // Null means the rate applies to the whole state.
            'municipality' => $this->whenLoaded('municipality', fn () => $this->municipality ? [
                'id' => $this->municipality->id,
                'name' => $this->municipality->name,
            ] : null),
            // Null means "a coordinar" for this exact zone.
            'cost' => $this->cost,
        ];
    }
}
