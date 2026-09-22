<?php

namespace App\Http\Resources\Admin;

use App\Models\ProductOptionValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductOptionValue
 */
class ProductOptionValueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_option_id' => $this->product_option_id,
            'value' => $this->value,
            'position' => $this->position,
            // How many live variants are built on this value. The panel uses
            // it to grey out the delete button; the backend refuses anyway.
            'variants_count' => $this->whenCounted('variants'),
        ];
    }
}
