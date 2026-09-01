<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable([
    'order_id',
    'disk',
    'path',
    'original_name',
    'mime_type',
    'size_bytes',
    'reference',
    'submitted_at',
])]
class PaymentProof extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isImage(): bool
    {
        return Str::startsWith($this->mime_type, 'image/');
    }

    /**
     * Proofs live on a private disk, so there is no permanent public URL by
     * design: the admin panel (Fase 5) gets a short-lived signed link instead.
     */
    public function temporaryUrl(int $minutes = 5): string
    {
        return Storage::disk($this->disk)->temporaryUrl($this->path, now()->addMinutes($minutes));
    }
}
