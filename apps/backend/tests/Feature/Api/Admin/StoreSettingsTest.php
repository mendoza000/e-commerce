<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\PaymentMethod;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StoreSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Currency $usd;

    private Currency $ves;

    private StoreSetting $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingFromAdminPanel();
        Storage::fake('public');

        $this->usd = Currency::factory()->create(['code' => 'USD']);
        $this->ves = Currency::factory()->create(['code' => 'VES']);

        $this->store = StoreSetting::factory()
            ->accepting([$this->usd, $this->ves])
            ->create(['store_name' => 'Tienda Demo']);
    }

    private function owner(): User
    {
        return User::factory()->owner()->create();
    }

    // -----------------------------------------------------------------
    // Reading
    // -----------------------------------------------------------------

    public function test_it_returns_the_store_configuration(): void
    {
        $this->actingAs($this->owner())
            ->getJson('/api/admin/settings')
            ->assertOk()
            ->assertJsonPath('data.store_name', 'Tienda Demo')
            ->assertJsonPath('data.base_currency.code', 'USD')
            ->assertJsonCount(2, 'data.enabled_currencies');
    }

    /**
     * Changing the base currency is allowed with no rate in place — forbidding
     * it would make the change impossible to ever carry out — so the panel has
     * to be able to see the gap instead.
     */
    public function test_it_flags_an_enabled_currency_that_has_no_rate_yet(): void
    {
        $response = $this->actingAs($this->owner())->getJson('/api/admin/settings')->assertOk();

        $currencies = collect($response->json('data.enabled_currencies'))->keyBy('code');

        $this->assertTrue($currencies['USD']['is_base']);
        $this->assertTrue($currencies['USD']['has_rate']);
        $this->assertFalse($currencies['VES']['has_rate']);

        ExchangeRate::factory()->create([
            'from_currency_id' => $this->usd->id,
            'to_currency_id' => $this->ves->id,
        ]);

        $this->actingAs($this->owner())
            ->getJson('/api/admin/settings')
            ->assertOk()
            ->assertJsonPath('data.enabled_currencies.1.has_rate', true);
    }

    // -----------------------------------------------------------------
    // Updating
    // -----------------------------------------------------------------

    public function test_it_updates_the_name_the_colours_and_the_whatsapp_number(): void
    {
        $this->actingAs($this->owner())
            ->putJson('/api/admin/settings', [
                'store_name' => 'Tienda Nueva',
                'primary_color' => '#1a2b3c',
                'secondary_color' => '#ffffff',
                'whatsapp_number' => '+584129999999',
            ])
            ->assertOk()
            ->assertJsonPath('data.store_name', 'Tienda Nueva')
            ->assertJsonPath('data.primary_color', '#1a2b3c')
            ->assertJsonPath('data.whatsapp_number', '+584129999999');
    }

    public function test_a_colour_that_is_not_hexadecimal_is_rejected(): void
    {
        $this->actingAs($this->owner())
            ->putJson('/api/admin/settings', ['primary_color' => 'azul'])
            ->assertStatus(422)
            ->assertJsonPath('error.fields.primary_color.0', 'El color debe ser hexadecimal, por ejemplo #1a2b3c.');
    }

    public function test_a_whatsapp_number_with_letters_is_rejected(): void
    {
        $this->actingAs($this->owner())
            ->putJson('/api/admin/settings', ['whatsapp_number' => '0412-LLAMAME'])
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['fields' => ['whatsapp_number']]]);
    }

    public function test_it_syncs_the_enabled_currencies(): void
    {
        $cop = Currency::factory()->create(['code' => 'COP']);

        $this->actingAs($this->owner())
            ->putJson('/api/admin/settings', [
                'enabled_currencies' => [$this->usd->id, $cop->id],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data.enabled_currencies');

        $this->assertEqualsCanonicalizing(
            [$this->usd->id, $cop->id],
            $this->store->fresh()->enabledCurrencies->pluck('id')->all(),
        );
    }

    public function test_it_changes_the_base_currency(): void
    {
        $this->actingAs($this->owner())
            ->putJson('/api/admin/settings', ['base_currency_id' => $this->ves->id])
            ->assertOk()
            ->assertJsonPath('data.base_currency.code', 'VES');
    }

    /**
     * Every price is expressed in the base currency, so a base the store does
     * not accept would leave the storefront quoting in something it refuses to
     * be paid in.
     */
    public function test_the_base_currency_has_to_be_one_of_the_enabled_ones(): void
    {
        $cop = Currency::factory()->create(['code' => 'COP']);

        $this->actingAs($this->owner())
            ->putJson('/api/admin/settings', ['base_currency_id' => $cop->id])
            ->assertStatus(422)
            ->assertJsonPath(
                'error.fields.enabled_currencies.0',
                'La moneda base tiene que estar entre las monedas habilitadas.',
            );

        $this->assertSame($this->usd->id, $this->store->fresh()->base_currency_id);
    }

    public function test_the_base_currency_can_be_changed_and_enabled_in_the_same_request(): void
    {
        $cop = Currency::factory()->create(['code' => 'COP']);

        $this->actingAs($this->owner())
            ->putJson('/api/admin/settings', [
                'base_currency_id' => $cop->id,
                'enabled_currencies' => [$cop->id, $this->usd->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.base_currency.code', 'COP');
    }

    public function test_the_store_cannot_end_up_accepting_nothing(): void
    {
        $this->actingAs($this->owner())
            ->putJson('/api/admin/settings', ['enabled_currencies' => []])
            ->assertStatus(422);
    }

    /**
     * Otherwise the storefront would keep offering a way to pay in a currency
     * the store says it does not accept.
     */
    public function test_a_currency_an_active_payment_method_charges_in_cannot_be_disabled(): void
    {
        PaymentMethod::factory()->create(['currency_id' => $this->ves->id, 'is_active' => true]);

        $this->actingAs($this->owner())
            ->putJson('/api/admin/settings', ['enabled_currencies' => [$this->usd->id]])
            ->assertStatus(422)
            ->assertJsonPath(
                'error.fields.enabled_currencies.0',
                'No puedes deshabilitar VES: hay métodos de pago activos que cobran en esa moneda.',
            );

        $this->assertCount(2, $this->store->fresh()->enabledCurrencies);
    }

    public function test_the_same_currency_can_be_disabled_once_that_method_is_off(): void
    {
        PaymentMethod::factory()->inactive()->create(['currency_id' => $this->ves->id]);

        $this->actingAs($this->owner())
            ->putJson('/api/admin/settings', ['enabled_currencies' => [$this->usd->id]])
            ->assertOk()
            ->assertJsonCount(1, 'data.enabled_currencies');
    }

    // -----------------------------------------------------------------
    // Logo
    // -----------------------------------------------------------------

    public function test_it_uploads_a_logo(): void
    {
        $path = $this->actingAs($this->owner())
            ->postJson('/api/admin/settings/logo', ['logo' => UploadedFile::fake()->image('logo.png', 900, 900)])
            ->assertOk()
            ->json('data.logo_path');

        $this->assertStringStartsWith('store/', $path);
        // Re-encoded by the shared ImageStorageService, like every other upload.
        $this->assertStringEndsWith('.jpg', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_replacing_the_logo_removes_the_file_the_old_one_pointed_at(): void
    {
        $owner = $this->owner();

        $first = $this->actingAs($owner)
            ->postJson('/api/admin/settings/logo', ['logo' => UploadedFile::fake()->image('viejo.png')])
            ->json('data.logo_path');

        $second = $this->actingAs($owner)
            ->postJson('/api/admin/settings/logo', ['logo' => UploadedFile::fake()->image('nuevo.png')])
            ->assertOk()
            ->json('data.logo_path');

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_it_removes_the_logo(): void
    {
        $owner = $this->owner();

        $path = $this->actingAs($owner)
            ->postJson('/api/admin/settings/logo', ['logo' => UploadedFile::fake()->image('logo.png')])
            ->json('data.logo_path');

        $this->actingAs($owner)
            ->deleteJson('/api/admin/settings/logo')
            ->assertOk()
            ->assertJsonPath('data.logo_path', null)
            ->assertJsonPath('data.logo_url', null);

        Storage::disk('public')->assertMissing($path);
    }

    public function test_a_file_that_is_not_an_image_is_rejected(): void
    {
        $this->actingAs($this->owner())
            ->postJson('/api/admin/settings/logo', [
                'logo' => UploadedFile::fake()->create('manual.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['fields' => ['logo']]]);
    }

    public function test_an_oversized_logo_is_rejected(): void
    {
        config(['commerce.store_logo.max_kilobytes' => 100]);

        $this->actingAs($this->owner())
            ->postJson('/api/admin/settings/logo', [
                'logo' => UploadedFile::fake()->image('enorme.png')->size(500),
            ])
            ->assertStatus(422);
    }

    // -----------------------------------------------------------------
    // Permissions
    // -----------------------------------------------------------------

    public function test_staff_cannot_read_or_write_the_store_configuration(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->getJson('/api/admin/settings')->assertStatus(403);

        $this->actingAs($staff)
            ->putJson('/api/admin/settings', ['store_name' => 'Mía Ahora'])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');

        $this->actingAs($staff)
            ->postJson('/api/admin/settings/logo', ['logo' => UploadedFile::fake()->image('logo.png')])
            ->assertStatus(403);

        $this->actingAs($staff)->deleteJson('/api/admin/settings/logo')->assertStatus(403);

        $this->assertSame('Tienda Demo', $this->store->fresh()->store_name);
    }

    public function test_an_anonymous_request_never_reaches_the_configuration(): void
    {
        $this->getJson('/api/admin/settings')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthenticated');
    }
}
