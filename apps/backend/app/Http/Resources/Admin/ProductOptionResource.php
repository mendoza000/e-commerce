<?php

namespace App\Http\Resources\Admin;

use App\Models\ProductOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductOption
 */
class ProductOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'name' => $this->name,
            'position' => $this->position,
            'values' => ProductOptionValueResource::collection($this->whenLoaded('values')),
        ];
    }
}
