<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A slug is the storefront's public identifier for a row, so the panel never
 * asks an admin to invent one: it derives the slug from the name and settles
 * collisions itself.
 *
 * Shared by Product and Category, the two things the storefront addresses by
 * slug (`GET /api/products/{slug}` and the `?category=` filter).
 */
trait GeneratesUniqueSlug
{
    /**
     * The first free slug derived from `$from`, appending -2, -3 … until one
     * is available. `$ignoreId` lets a row keep its own slug while updating.
     */
    public static function uniqueSlug(string $from, ?int $ignoreId = null): string
    {
        $base = Str::slug($from);

        // A name made entirely of characters Str::slug strips — an emoji, a
        // name in a non-latin script — would otherwise slugify to the empty
        // string, and every such row would collide with the previous one.
        if ($base === '') {
            $base = 'item';
        }

        $slug = $base;
        $suffix = 2;

        while (static::slugTaken($slug, $ignoreId)) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private static function slugTaken(string $slug, ?int $ignoreId): bool
    {
        $query = static::query()->where('slug', $slug);

        // The unique index counts soft-deleted rows, so this has to as well:
        // a slug that is free only in Eloquent's eyes still fails on insert.
        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            $query->withTrashed();
        }

        return $query
            ->when($ignoreId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }
}
