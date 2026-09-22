<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductOptionValue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Catalogue images, and the two rules that are easy to get wrong once several
 * admins are uploading at once: a product has exactly one primary image, and
 * its images are ordered without gaps.
 *
 * Images hang off a product and, optionally, off one of its option values —
 * the photos of the colour "Rojo", which every Rojo variant inherits without
 * anyone duplicating them (PRD, sección de catálogo). They are never attached
 * to a variant: that is what would force the duplication.
 */
class ProductImageService
{
    public function __construct(private readonly ImageStorageService $images) {}

    /**
     * @throws ValidationException
     */
    public function store(Product $product, UploadedFile $file, ?int $optionValueId): ProductImage
    {
        $this->guardBelongsToProduct($product, $optionValueId);
        $this->guardRoomForOneMore($product);

        $path = $this->images->store(
            $file,
            $this->disk(),
            (string) config('commerce.product_image.directory').'/'.$product->getKey(),
            (int) config('commerce.product_image.image_max_width'),
            (int) config('commerce.product_image.image_quality'),
        );

        return DB::transaction(function () use ($product, $path, $optionValueId) {
            $isFirst = $product->images()->doesntExist();

            return $product->images()->create([
                'product_option_value_id' => $optionValueId,
                'path' => $path,
                'position' => (int) $product->images()->max('position') + ($isFirst ? 0 : 1),
                // The first image a product ever gets is its primary one:
                // otherwise a product would show no cover until someone
                // remembered to pick one.
                'is_primary' => $isFirst,
            ]);
        });
    }

    /**
     * Drops the row and the file behind it, and hands the primary flag to the
     * next image so the product never ends up with images but no cover.
     *
     * The row goes first: a file left on disk with no row is recoverable
     * clutter, while a row pointing at a file that is gone shows the panel a
     * broken image.
     */
    public function delete(ProductImage $image): void
    {
        $disk = $this->disk();
        $path = $image->path;

        DB::transaction(function () use ($image) {
            $wasPrimary = $image->is_primary;
            $productId = $image->product_id;

            $image->delete();

            if (! $wasPrimary) {
                return;
            }

            ProductImage::query()
                ->where('product_id', $productId)
                ->orderBy('position')
                ->orderBy('id')
                ->first()
                ?->update(['is_primary' => true]);
        });

        $this->images->delete($disk, $path);
    }

    /**
     * Rewrites the display order from the list the panel sends, which must
     * name every image of the product exactly once — a partial list would
     * leave the images it omits sharing positions with the ones it moved.
     *
     * @param  array<int, int>  $imageIds
     * @return Collection<int, ProductImage>
     *
     * @throws ValidationException
     */
    public function reorder(Product $product, array $imageIds): Collection
    {
        $ids = array_map('intval', $imageIds);
        $current = $product->images()->pluck('id')->all();

        sort($ids);
        $sortedCurrent = $current;
        sort($sortedCurrent);

        if ($ids !== $sortedCurrent) {
            throw ValidationException::withMessages([
                'images' => ['La lista debe incluir exactamente una vez cada imagen de este producto.'],
            ]);
        }

        DB::transaction(function () use ($product, $imageIds) {
            foreach (array_values($imageIds) as $position => $imageId) {
                $product->images()->whereKey($imageId)->update(['position' => $position]);
            }
        });

        return $this->ordered($product);
    }

    /**
     * @return Collection<int, ProductImage>
     */
    public function makePrimary(ProductImage $image): Collection
    {
        DB::transaction(function () use ($image) {
            ProductImage::query()
                ->where('product_id', $image->product_id)
                ->whereKeyNot($image->getKey())
                ->update(['is_primary' => false]);

            $image->update(['is_primary' => true]);
        });

        return $this->ordered($image->product);
    }

    /**
     * @return Collection<int, ProductImage>
     */
    public function ordered(Product $product): Collection
    {
        return $product->images()
            ->orderByDesc('is_primary')
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    private function disk(): string
    {
        return (string) config('commerce.product_image.disk');
    }

    /**
     * @throws ValidationException
     */
    private function guardBelongsToProduct(Product $product, ?int $optionValueId): void
    {
        if ($optionValueId === null) {
            return;
        }

        $belongs = ProductOptionValue::query()
            ->whereKey($optionValueId)
            ->whereHas('option', fn ($query) => $query->where('product_id', $product->getKey()))
            ->exists();

        if (! $belongs) {
            throw ValidationException::withMessages([
                'product_option_value_id' => ['Ese valor de opción no pertenece a este producto.'],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function guardRoomForOneMore(Product $product): void
    {
        $max = (int) config('commerce.catalog.max_images_per_product');

        if ($product->images()->count() >= $max) {
            throw ValidationException::withMessages([
                'image' => ["Este producto ya tiene el máximo de {$max} imágenes."],
            ]);
        }
    }
}
