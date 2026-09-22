<?php

namespace App\Models;

use App\Models\Concerns\GeneratesUniqueSlug;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

#[Fillable(['category_id', 'name', 'slug', 'description', 'base_price', 'is_active'])]
class Product extends Model
{
    use GeneratesUniqueSlug, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:6',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    // ---------------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------------

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    // ---------------------------------------------------------------------
    // Lifecycle
    // ---------------------------------------------------------------------

    /**
     * "Deleting" a product from the panel is a soft delete, never a real one:
     * `order_items.product_variant_id` points at its variants, and a store has
     * to be able to answer "what exactly did I sell in March?" long after the
     * product left the catalogue.
     *
     * SoftDeletes does not cascade, so the variants are archived here too —
     * otherwise they would stay orderable through their ids while their
     * product is gone.
     */
    public function archive(): void
    {
        DB::transaction(function () {
            $this->delete();

            // The relation's default scope excludes trashed rows, so this only
            // touches the variants that were still live.
            $this->variants()->delete();
        });
    }

    /**
     * Brings the product back with every variant it ever had, including any
     * the admin had retired one by one beforehand.
     *
     * Restoring only the variants this product's archiving took would be
     * nicer, but there is nothing to tell them apart by: `deleted_at` is a
     * second-precision column, so a variant deleted in the same second as the
     * product is indistinguishable from one the archiving took. Bringing them
     * all back is the rule that always holds — and it is the safe direction,
     * since re-retiring a variant is one click while a silently missing one is
     * invisible.
     */
    public function unarchive(): void
    {
        DB::transaction(function () {
            $this->restore();

            $this->variants()->onlyTrashed()->restore();
        });
    }

    /**
     * Whether any of this product's variants is holding units for an order
     * that is still open. Archiving it would take stock out from under a
     * customer who is on their way to pay.
     */
    public function hasLiveReservations(): bool
    {
        return $this->variants()->where('reserved_quantity', '>', 0)->exists();
    }
}
