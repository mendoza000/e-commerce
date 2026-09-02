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
            // The disk comes from config, not from a literal: catalogue images
            // are on `public` in development and could be on S3 in production,
            // and only the config knows which. Unlike payment proofs, they are
            // served by the web server through the storage:link symlink — they
            // are meant to be seen by anyone browsing the store.
            'url' => Storage::disk(config('commerce.product_image.disk'))->url($this->path),
            'position' => $this->position,
            'is_primary' => $this->is_primary,
            'product_option_value_id' => $this->product_option_value_id,
        ];
    }
}
