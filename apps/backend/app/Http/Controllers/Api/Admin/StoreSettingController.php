<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\StoreLogoRequest;
use App\Http\Requests\Api\Admin\StoreSettingUpdateRequest;
use App\Http\Resources\Admin\StoreSettingResource;
use App\Models\StoreSetting;
use App\Services\ImageStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * The store's own configuration: name, look, contact number, and which
 * currencies it deals in.
 *
 * `store_settings` is a single-row table (docs/decisions.md), so there is no
 * id in any of these paths — there is one store per installation, and the
 * endpoint resolves it rather than being told which one.
 */
class StoreSettingController extends Controller
{
    public function show(): JsonResponse
    {
        return StoreSettingResource::make($this->detail(StoreSetting::current()))->response();
    }

    public function update(StoreSettingUpdateRequest $request): JsonResponse
    {
        $settings = StoreSetting::current();
        $attributes = $request->validated();

        DB::transaction(function () use ($settings, $attributes) {
            $settings->update(collect($attributes)->except('enabled_currencies')->all());

            if (array_key_exists('enabled_currencies', $attributes)) {
                $settings->enabledCurrencies()->sync($attributes['enabled_currencies']);
            }
        });

        return StoreSettingResource::make($this->detail($settings->fresh()))->response();
    }

    /**
     * Replaces the logo, then deletes the file the old one pointed at.
     *
     * That order matters: a file left on disk with no row is recoverable
     * clutter, while a row pointing at a file that is already gone shows every
     * visitor a broken image.
     */
    public function uploadLogo(StoreLogoRequest $request, ImageStorageService $images): JsonResponse
    {
        $settings = StoreSetting::current();
        $previous = $settings->logo_path;

        $path = $images->store(
            $request->file('logo'),
            (string) config('commerce.store_logo.disk'),
            (string) config('commerce.store_logo.directory'),
            (int) config('commerce.store_logo.image_max_width'),
            (int) config('commerce.store_logo.image_quality'),
        );

        $settings->update(['logo_path' => $path]);

        if ($previous !== null) {
            $images->delete((string) config('commerce.store_logo.disk'), $previous);
        }

        return StoreSettingResource::make($this->detail($settings->fresh()))->response();
    }

    public function deleteLogo(ImageStorageService $images): JsonResponse
    {
        $settings = StoreSetting::current();
        $previous = $settings->logo_path;

        $settings->update(['logo_path' => null]);

        if ($previous !== null) {
            $images->delete((string) config('commerce.store_logo.disk'), $previous);
        }

        return StoreSettingResource::make($this->detail($settings->fresh()))->response();
    }

    private function detail(StoreSetting $settings): StoreSetting
    {
        return $settings->load(['baseCurrency', 'enabledCurrencies']);
    }
}
