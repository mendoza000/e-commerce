<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'path' => $this->path,
            'url' => Storage::disk('public')->url($this->path),
            'position' => $this->position,
            'is_primary' => $this->is_primary,
            'product_option_value_id' => $this->product_option_value_id,
        ];
    }
}
