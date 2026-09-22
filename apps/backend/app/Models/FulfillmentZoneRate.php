<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['fulfillment_method_id', 'state_id', 'municipality_id', 'cost'])]
class FulfillmentZoneRate extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:6',
        ];
    }

    public function fulfillmentMethod(): BelongsTo
    {
        return $this->belongsTo(FulfillmentMethod::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }
}
