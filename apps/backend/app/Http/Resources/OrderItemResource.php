<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'product_name' => $this->product_name,
            'variant_description' => $this->variant_description,
            'sku' => $this->sku,
            'unit_price' => $this->unit_price,
            'quantity' => $this->quantity,
            'subtotal' => $this->subtotal,
        ];
    }
}
