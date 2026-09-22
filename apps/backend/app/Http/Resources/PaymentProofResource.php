<?php

namespace App\Http\Resources;

use App\Models\PaymentProof;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaymentProof
 */
class PaymentProofResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // Neither the disk nor the storage path is ever exposed: the file
            // is private and only the admin panel gets a signed link to it.
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'reference' => $this->reference,
            'submitted_at' => $this->submitted_at?->toISOString(),
        ];
    }
}
