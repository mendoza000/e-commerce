<?php

namespace Tests\Feature\Api;

use App\Models\Currency;
use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * GET /api/store — the store's public identity, so the storefront stops
 * having the name, logo and colours compiled into it.
 */
class StoreShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_what_the_storefront_needs_to_render_the_store(): void
    {
        $usd = Currency::factory()->create(['code' => 'USD', 'symbol' => '$', 'decimal_places' => 2]);

        StoreSetting::factory()->accepting([$usd])->create([
            'store_name' => 'Tienda Demo',
            'primary_color' => '#1a2b3c',
            'secondary_color' => '#ffffff',
            'whatsapp_number' => '+584121234567',
        ]);

        $this->getJson('/api/store')
            ->assertOk()
            ->assertJsonPath('data.store_name', 'Tienda Demo')
            ->assertJsonPath('data.primary_color', '#1a2b3c')
            ->assertJsonPath('data.secondary_color', '#ffffff')
            ->assertJsonPath('data.whatsapp_number', '+584121234567')
            ->assertJsonPath('data.base_currency.code', 'USD')
            ->assertJsonPath('data.logo_url', null);
    }

    public function test_the_logo_comes_back_as_a_url_the_browser_can_fetch(): void
    {
        Storage::fake('public');

        $usd = Currency::factory()->create();
        StoreSetting::factory()->accepting([$usd])->create(['logo_path' => 'store/logo.jpg']);

        $url = $this->getJson('/api/store')->assertOk()->json('data.logo_url');

        $this->assertStringContainsString('store/logo.jpg', $url);
    }

    /**
     * It is a public endpoint on purpose — the name and logo are already
     * printed on every page — but it must not leak the configuration behind
     * them.
     */
    public function test_it_does_not_expose_the_internals_of_the_configuration(): void
    {
        $usd = Currency::factory()->create();
        $ves = Currency::factory()->create();
        StoreSetting::factory()->accepting([$usd, $ves])->create();

        $data = $this->getJson('/api/store')->assertOk()->json('data');

        $this->assertArrayNotHasKey('id', $data);
        $this->assertArrayNotHasKey('enabled_currencies', $data);
        $this->assertArrayNotHasKey('updated_at', $data);
    }

    public function test_it_needs_no_session(): void
    {
        $usd = Currency::factory()->create();
        StoreSetting::factory()->accepting([$usd])->create();

        $this->getJson('/api/store')->assertOk();
    }
}
