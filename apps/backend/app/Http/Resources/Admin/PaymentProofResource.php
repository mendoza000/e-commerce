<?php

namespace App\Http\Resources\Admin;

use App\Models\PaymentProof;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The same file the customer uploaded, described for the panel. The disk and
 * the storage path stay hidden here too: `download_url` points at the
 * streaming endpoint, which is the only way in (see Admin\PaymentProofController).
 *
 * @mixin PaymentProof
 */
class PaymentProofResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            // Whether the panel can render it in place or has to offer it as a
            // download: PDFs are accepted alongside images.
            'is_image' => $this->isImage(),
            'reference' => $this->reference,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'download_url' => route('admin.payment-proofs.show', ['payment_proof' => $this->id]),
        ];
    }
}
