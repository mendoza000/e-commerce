<?php

namespace Tests\Feature\Api\Admin;

use App\Domain\Enums\ExchangeRateMode;
use App\Domain\Enums\ExchangeRateProviderType;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\ExchangeRateSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExchangeRateManagementTest extends TestCase
{
    use RefreshDatabase;

    private Currency $usd;

    private Currency $ves;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingFromAdminPanel();

        $this->usd = Currency::factory()->create(['code' => 'USD']);
        $this->ves = Currency::factory()->create(['code' => 'VES']);
    }

    private function owner(): User
    {
        return User::factory()->owner()->create();
    }

    // -----------------------------------------------------------------
    // Registering a rate by hand
    // -----------------------------------------------------------------

    public function test_an_owner_registers_a_manual_rate(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->postJson('/api/admin/exchange-rates', [
                'from_currency_id' => $this->usd->id,
                'to_currency_id' => $this->ves->id,
                'rate' => '737.500000',
                'reference_amount' => '100',
            ])
            ->assertCreated()
            ->assertJsonPath('data.rate', '737.500000')
            ->assertJsonPath('data.source', 'manual')
            ->assertJsonPath('data.from_currency.code', 'USD')
            ->assertJsonPath('data.to_currency.code', 'VES')
            ->assertJsonPath('data.created_by.name', $owner->name);
    }

    /**
     * The history is immutable: correcting a rate means registering the right
     * one now, never editing the row that priced yesterday's orders.
     */
    public function test_registering_again_leaves_the_previous_rate_untouched(): void
    {
        $owner = $this->owner();
        $payload = [
            'from_currency_id' => $this->usd->id,
            'to_currency_id' => $this->ves->id,
        ];

        $first = $this->actingAs($owner)
            ->postJson('/api/admin/exchange-rates', [...$payload, 'rate' => '700'])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($owner)
            ->postJson('/api/admin/exchange-rates', [...$payload, 'rate' => '740'])
            ->assertCreated();

        $this->assertSame(2, ExchangeRate::query()->count());
        $this->assertSame('700.000000', ExchangeRate::query()->findOrFail($first)->rate);
    }

    public function test_there_is_no_way_to_edit_or_delete_a_stored_rate(): void
    {
        $rate = ExchangeRate::factory()->create([
            'from_currency_id' => $this->usd->id,
            'to_currency_id' => $this->ves->id,
        ]);

        $owner = $this->owner();

        // Not "method not allowed": the URI itself does not exist, because no
        // endpoint was ever written that could change a stored rate.
        $this->actingAs($owner)->patchJson("/api/admin/exchange-rates/{$rate->id}", ['rate' => '1'])
            ->assertStatus(404);
        $this->actingAs($owner)->deleteJson("/api/admin/exchange-rates/{$rate->id}")
            ->assertStatus(404);

        $this->assertSame(1, ExchangeRate::query()->count());
    }

    public function test_a_rate_of_zero_is_rejected(): void
    {
        $this->actingAs($this->owner())
            ->postJson('/api/admin/exchange-rates', [
                'from_currency_id' => $this->usd->id,
                'to_currency_id' => $this->ves->id,
                'rate' => '0',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.fields.rate.0', 'La tasa tiene que ser mayor que cero.');
    }

    public function test_a_currency_cannot_be_exchanged_for_itself(): void
    {
        $this->actingAs($this->owner())
            ->postJson('/api/admin/exchange-rates', [
                'from_currency_id' => $this->usd->id,
                'to_currency_id' => $this->usd->id,
                'rate' => '1',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.fields.to_currency_id.0', 'Una moneda no se cambia por sí misma.');
    }

    // -----------------------------------------------------------------
    // The history
    // -----------------------------------------------------------------

    public function test_the_history_comes_back_newest_first_and_filtered_by_pair(): void
    {
        $cop = Currency::factory()->create(['code' => 'COP']);

        ExchangeRate::factory()->create([
            'from_currency_id' => $this->usd->id,
            'to_currency_id' => $this->ves->id,
            'rate' => '700',
            'effective_at' => now()->subDay(),
        ]);
        ExchangeRate::factory()->create([
            'from_currency_id' => $this->usd->id,
            'to_currency_id' => $this->ves->id,
            'rate' => '740',
            'effective_at' => now(),
        ]);
        ExchangeRate::factory()->create([
            'from_currency_id' => $this->usd->id,
            'to_currency_id' => $cop->id,
        ]);

        $this->actingAs($this->owner())
            ->getJson("/api/admin/exchange-rates?from_currency_id={$this->usd->id}&to_currency_id={$this->ves->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.rate', '740.000000')
            ->assertJsonPath('data.1.rate', '700.000000');
    }

    public function test_the_history_separates_what_a_person_set_from_what_a_source_reported(): void
    {
        $owner = $this->owner();

        ExchangeRate::factory()->create([
            'from_currency_id' => $this->usd->id,
            'to_currency_id' => $this->ves->id,
            'created_by' => $owner->id,
        ]);
        ExchangeRate::factory()->automatic()->create([
            'from_currency_id' => $this->usd->id,
            'to_currency_id' => $this->ves->id,
            'effective_at' => now()->subHour(),
        ]);

        $this->actingAs($owner)
            ->getJson('/api/admin/exchange-rates?source=criptoya')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.created_by', null);
    }

    public function test_the_history_is_paginated(): void
    {
        ExchangeRate::factory()->count(5)->create([
            'from_currency_id' => $this->usd->id,
            'to_currency_id' => $this->ves->id,
        ]);

        $this->actingAs($this->owner())
            ->getJson('/api/admin/exchange-rates?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 5);
    }

    // -----------------------------------------------------------------
    // Pair configuration
    // -----------------------------------------------------------------

    public function test_it_lists_the_pairs_with_their_health_and_current_rate(): void
    {
        $setting = ExchangeRateSetting::factory()->criptoya()->create([
            'from_currency_id' => $this->usd->id,
            'to_currency_id' => $this->ves->id,
        ]);
        $setting->markFailed('CriptoYa respondió 503.');

        ExchangeRate::factory()->create([
            'from_currency_id' => $this->usd->id,
            'to_currency_id' => $this->ves->id,
            'rate' => '737',
        ]);

        $this->actingAs($this->owner())
            ->getJson('/api/admin/exchange-rate-settings')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.pair', 'USD/VES')
            ->assertJsonPath('data.0.health.status', 'failing')
            ->assertJsonPath('data.0.health.last_error', 'CriptoYa respondió 503.')
            ->assertJsonPath('data.0.latest_rate.rate', '737.000000');
    }

    public function test_a_manual_pair_that_never_ran_is_not_reported_as_failing(): void
    {
        ExchangeRateSetting::factory()->create([
            'from_currency_id' => $this->usd->id,
            'to_currency_id' => $this->ves->id,
        ]);

        $this->actingAs($this->owner())
            ->getJson('/api/admin/exchange-rate-settings')
            ->assertOk()
            ->assertJsonPath('data.0.health.status', 'manual')
            ->assertJsonPath('data.0.latest_rate', null);
    }

    public function test_it_configures_a_pair_for_automatic_refresh(): void
    {
        $this->actingAs($this->owner())
            ->postJson('/api/admin/exchange-rate-settings', [
                'from_currency_id' => $this->usd->id,
                'to_currency_id' => $this->ves->id,
                'mode' => ExchangeRateMode::Automatic->value,
                'provider' => ExchangeRateProviderType::CriptoYa->value,
                'frequency_minutes' => 60,
                'reference_amount' => '100',
            ])
            ->assertCreated()
            ->assertJsonPath('data.mode', 'automatic')
            ->assertJsonPath('data.provider', 'criptoya')
            ->assertJsonPath('data.health.status', 'pending');
    }

    public function test_the_same_pair_cannot_be_configured_twice(): void
    {
        ExchangeRateSetting::factory()->create([
            'from_currency_id' => $this->usd->id,
            'to_currency_id' => $this->ves->id,
        ]);

        $this->actingAs($this->owner())
            ->postJson('/api/admin/exchange-rate-settings', [
                'from_currency_id' => $this->usd->id,
                'to_currency_id' => $this->ves->id,
                'mode' => ExchangeRateMode::Manual->value,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.fields.to_currency_id.0', 'Ya existe una configuración para ese par de monedas.');
    }

    public function test_a_provider_the_registry_does_not_know_is_rejected(): void
    {
        $this->actingAs($this->owner())
            ->postJson('/api/admin/exchange-rate-settings', [
                'from_currency_id' => $this->usd->id,
                'to_currency_id' => $this->ves->id,
                'mode' => ExchangeRateMode::Automatic->value,
                'provider' => 'una-fuente-inventada',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.fields.provider.0', 'Esa fuente de tasas no existe.');
    }

    /**
     * The refresh command skips a manual provider, so the pair would look
     * configured while never updating.
     */
    public function test_an_automatic_pair_needs_a_source_that_actually_fetches(): void
    {
        $this->actingAs($this->owner())
            ->postJson('/api/admin/exchange-rate-settings', [
                'from_currency_id' => $this->usd->id,
                'to_currency_id' => $this->ves->id,
                'mode' => ExchangeRateMode::Automatic->value,
                'provider' => ExchangeRateProviderType::Manual->value,
            ])
            ->assertStatus(422)
            ->assertJsonPath(
                'error.fields.provider.0',
                'Un par automático necesita una fuente automática: elige una distinta de "manual".',
            );
    }

    public function test_switching_a_pair_to_automatic_without_naming_a_source_is_rejected(): void
    {
        $setting = ExchangeRateSetting::factory()->create([
            'from_currency_id' => $this->usd->id,
            'to_currency_id' => $this->ves->id,
        ]);

        $this->actingAs($this->owner())
            ->patchJson("/api/admin/exchange-rate-settings/{$setting->id}", [
                'mode' => ExchangeRateMode::Automatic->value,
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['fields' => ['provider']]]);
    }

    public function test_it_updates_the_frequency_and_deactivates_a_pair(): void
    {
        $setting = ExchangeRateSetting::factory()->criptoya()->create([
            'from_currency_id' => $this->usd->id,
            'to_currency_id' => $this->ves->id,
        ]);

        $this->actingAs($this->owner())
            ->patchJson("/api/admin/exchange-rate-settings/{$setting->id}", [
                'frequency_minutes' => 15,
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.frequency_minutes', 15)
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.health.status', 'inactive');
    }

    /**
     * Its refresh history describes the pair it ran for; pointing the row at
     * other currencies would make that history a lie.
     */
    public function test_the_pair_of_a_configuration_cannot_be_edited(): void
    {
        $cop = Currency::factory()->create(['code' => 'COP']);
        $setting = ExchangeRateSetting::factory()->create([
            'from_currency_id' => $this->usd->id,
            'to_currency_id' => $this->ves->id,
        ]);

        $this->actingAs($this->owner())
            ->patchJson("/api/admin/exchange-rate-settings/{$setting->id}", ['to_currency_id' => $cop->id])
            ->assertStatus(422)
            ->assertJsonPath(
                'error.fields.to_currency_id.0',
                'El par de monedas no se edita: elimina esta configuración y crea la otra.',
            );
    }

    public function test_deleting_a_configuration_leaves_the_rates_it_produced_in_place(): void
    {
        $setting = ExchangeRateSetting::factory()->create([
            'from_currency_id' => $this->usd->id,
            'to_currency_id' => $this->ves->id,
        ]);
        ExchangeRate::factory()->create([
            'from_currency_id' => $this->usd->id,
            'to_currency_id' => $this->ves->id,
        ]);

        $this->actingAs($this->owner())
            ->deleteJson("/api/admin/exchange-rate-settings/{$setting->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('exchange_rate_settings', ['id' => $setting->id]);
        $this->assertSame(1, ExchangeRate::query()->count());
    }

    // -----------------------------------------------------------------
    // Permissions
    // -----------------------------------------------------------------

    public function test_staff_cannot_see_or_set_rates(): void
    {
        $staff = User::factory()->staff()->create();
        $setting = ExchangeRateSetting::factory()->create([
            'from_currency_id' => $this->usd->id,
            'to_currency_id' => $this->ves->id,
        ]);

        $this->actingAs($staff)->getJson('/api/admin/exchange-rates')->assertStatus(403);
        $this->actingAs($staff)->getJson('/api/admin/exchange-rate-settings')->assertStatus(403);
        $this->actingAs($staff)->getJson('/api/admin/currencies')->assertStatus(403);

        $this->actingAs($staff)
            ->postJson('/api/admin/exchange-rates', [
                'from_currency_id' => $this->usd->id,
                'to_currency_id' => $this->ves->id,
                'rate' => '1000',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');

        $this->actingAs($staff)
            ->patchJson("/api/admin/exchange-rate-settings/{$setting->id}", ['is_active' => false])
            ->assertStatus(403);

        $this->assertSame(0, ExchangeRate::query()->count());
    }

    public function test_the_currency_catalogue_is_available_to_build_the_pickers(): void
    {
        $this->actingAs($this->owner())
            ->getJson('/api/admin/currencies')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.code', 'USD');
    }
}
