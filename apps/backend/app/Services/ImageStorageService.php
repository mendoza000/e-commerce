<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * The one place that knows how an uploaded file is written to disk.
 *
 * Payment proofs and product images have nothing else in common — one is
 * private and streamed through the API, the other is public and served by the
 * web server — but both arrive as a photo taken with a phone, and both need the
 * same treatment: re-encode, downscale, store under a name the uploader did not
 * choose. That logic lived inside PaymentProofService until the catalogue
 * needed it too; it is here so there is only one copy of it.
 *
 * Anything that is not an image (a PDF proof, for instance) is stored
 * untouched: re-encoding it would destroy it.
 */
class ImageStorageService
{
    /**
     * Writes the file and returns its path relative to the disk root.
     *
     * The stored name is always a UUID: a client-supplied filename is
     * attacker-controlled text, and keeping it would leak whatever the
     * customer happened to call the file.
     */
    public function store(
        UploadedFile $file,
        string $disk,
        string $directory,
        int $maxWidth,
        int $quality,
    ): string {
        $directory = trim($directory, '/');

        if (! $this->isImage($file)) {
            return $file->store($directory, ['disk' => $disk]);
        }

        $encoded = (new ImageManager(new Driver))
            ->read($file->getRealPath())
            // scaleDown never upscales: a small image is left at its own size
            // rather than blown up into a blurry one.
            ->scaleDown(width: $maxWidth)
            ->toJpeg(quality: $quality);

        $path = $directory.'/'.Str::uuid()->toString().'.jpg';

        Storage::disk($disk)->put($path, (string) $encoded);

        return $path;
    }

    /**
     * Removes a stored file, tolerating one that is already gone — a delete
     * must not fail because a previous cleanup got there first.
     */
    public function delete(string $disk, string $path): void
    {
        Storage::disk($disk)->delete($path);
    }

    private function isImage(UploadedFile $file): bool
    {
        return Str::startsWith($file->getMimeType() ?: '', 'image/');
    }
}
