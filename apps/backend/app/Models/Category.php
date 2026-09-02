<?php

namespace App\Models;

use App\Models\Concerns\GeneratesUniqueSlug;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'parent_id', 'description'])]
class Category extends Model
{
    use GeneratesUniqueSlug, HasFactory;

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Whether anything still hangs off this category.
     *
     * Both foreign keys are `nullOnDelete`, so the database would let the
     * delete through and silently un-categorise every product underneath. The
     * panel refuses instead: moving those products somewhere else is a
     * decision, and it belongs to the admin, not to a cascade rule.
     */
    public function isInUse(): bool
    {
        return $this->products()->exists() || $this->children()->exists();
    }
}
