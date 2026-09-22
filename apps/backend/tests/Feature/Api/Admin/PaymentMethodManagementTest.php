<?php

namespace Tests\Feature\Api\Admin;

use App\Domain\Enums\PaymentMethodType;
use App\Models\Currency;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMethodManagementTest extends TestCase
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

        StoreSetting::factory()->accepting([$this->usd, $this->ves])->create();
    }

    private function owner(): User
    {
        return User::factory()->owner()->create();
    }

    // -----------------------------------------------------------------
    // Reading
    // -----------------------------------------------------------------

    public function test_it_lists_the_methods_in_the_order_the_storefront_shows_them(): void
    {
        PaymentMethod::factory()->zelle()->create(['currency_id' => $this->usd->id, 'position' => 1]);
        PaymentMethod::factory()->create(['currency_id' => $this->ves->id, 'position' => 0]);

        $this->actingAs($this->owner())
            ->getJson('/api/admin/payment-methods')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.type', 'pago_movil')
            ->assertJsonPath('data.1.type', 'zelle')
            ->assertJsonPath('data.0.orders_count', 0);
    }

    /**
     * So the panel can draw the right form per type without hardcoding a field
     * list that would drift from the providers.
     */
    public function test_it_publishes_the_account_fields_each_type_expects(): void
    {
        $response = $this->actingAs($this->owner())
            ->getJson('/api/admin/payment-method-types')
            ->assertOk()
            ->assertJsonCount(count(PaymentMethodType::cases()), 'data');

        $types = collect($response->json('data'))->keyBy('value');

        $this->assertSame('Zelle', $types['zelle']['label']);
        $this->assertSame(['email', 'holder_name'], $types['zelle']['instruction_fields']);
        $this->assertSame(['contact_phone'], $types['efectivo_contra_entrega']['instruction_fields']);
    }

    // -----------------------------------------------------------------
    // Creating and editing
    // -----------------------------------------------------------------

    public function test_it_creates_a_method_and_appends_it_to_the_checkout_list(): void
    {
        PaymentMethod::factory()->create(['currency_id' => $this->ves->id, 'position' => 0]);

        $this->actingAs($this->owner())
            ->postJson('/api/admin/payment-methods', [
                'type' => PaymentMethodType::Zelle->value,
                'label' => 'Zelle',
                'currency_id' => $this->usd->id,
                'instructions' => ['email' => 'pagos@tienda.test', 'holder_name' => 'Tienda Demo'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'zelle')
            ->assertJsonPath('data.position', 1)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.requires_proof', true)
            ->assertJsonPath('data.instructions.email', 'pagos@tienda.test');
    }

    public function test_cash_on_delivery_is_published_as_needing_no_proof(): void
    {
        $this->actingAs($this->owner())
            ->postJson('/api/admin/payment-methods', [
                'type' => PaymentMethodType::EfectivoContraEntrega->value,
                'label' => 'Efectivo contra entrega',
                'currency_id' => $this->usd->id,
                'instructions' => ['contact_phone' => '04121234567'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.requires_proof', false);
    }

    /**
     * The same inconsistency the settings endpoint refuses from the other
     * side: a method charging in something the store says it does not accept.
     */
    public function test_a_method_cannot_charge_in_a_currency_the_store_does_not_accept(): void
    {
        $cop = Currency::factory()->create(['code' => 'COP']);

        $this->actingAs($this->owner())
            ->postJson('/api/admin/payment-methods', [
                'type' => PaymentMethodType::Zelle->value,
                'label' => 'Zelle en pesos',
                'currency_id' => $cop->id,
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['fields' => ['currency_id']]]);

        $this->assertSame(0, PaymentMethod::query()->count());
    }

    /**
     * A key the type does not read would be stored and never shown to anyone.
     */
    public function test_account_fields_that_do_not_belong_to_the_type_are_rejected(): void
    {
        $this->actingAs($this->owner())
            ->postJson('/api/admin/payment-methods', [
                'type' => PaymentMethodType::Zelle->value,
                'label' => 'Zelle',
                'currency_id' => $this->usd->id,
                'instructions' => ['email' => 'pagos@tienda.test', 'bank_code' => '0102'],
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['fields' => ['instructions']]]);
    }

    public function test_notes_are_accepted_for_every_type(): void
    {
        $this->actingAs($this->owner())
            ->postJson('/api/admin/payment-methods', [
                'type' => PaymentMethodType::Zelle->value,
                'label' => 'Zelle',
                'currency_id' => $this->usd->id,
                'instructions' => ['email' => 'pagos@tienda.test', 'notes' => 'Indica tu número de orden.'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.instructions.notes', 'Indica tu número de orden.');
    }

    public function test_it_edits_the_account_details_and_the_visible_label(): void
    {
        $method = PaymentMethod::factory()->create(['currency_id' => $this->ves->id]);

        $this->actingAs($this->owner())
            ->patchJson("/api/admin/payment-methods/{$method->id}", [
                'label' => 'Pago Móvil Banesco',
                'instructions' => [
                    'bank' => 'Banesco',
                    'bank_code' => '0134',
                    'phone' => '04149999999',
                    'document_number' => 'V-87654321',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.label', 'Pago Móvil Banesco')
            ->assertJsonPath('data.instructions.bank', 'Banesco');

        // Replaced wholesale, not merged: clearing a stale field has to work.
        $this->assertSame('0134', $method->fresh()->instructionValue('bank_code'));
    }

    public function test_a_method_is_deactivated_without_being_deleted(): void
    {
        $method = PaymentMethod::factory()->create(['currency_id' => $this->ves->id]);

        $this->actingAs($this->owner())
            ->patchJson("/api/admin/payment-methods/{$method->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('payment_methods', ['id' => $method->id]);
    }

    /**
     * The type decides the provider, the account fields and whether a proof is
     * needed; changing it would reinterpret the stored instructions as another
     * method's fields.
     */
    public function test_the_type_of_a_method_cannot_be_changed(): void
    {
        $method = PaymentMethod::factory()->create(['currency_id' => $this->ves->id]);

        $this->actingAs($this->owner())
            ->patchJson("/api/admin/payment-methods/{$method->id}", [
                'type' => PaymentMethodType::Zelle->value,
            ])
            ->assertStatus(422)
            ->assertJsonPath(
                'error.fields.type.0',
                'El tipo de un método de pago no se cambia: crea otro método y desactiva este.',
            );

        $this->assertSame(PaymentMethodType::PagoMovil, $method->fresh()->type);
    }

    // -----------------------------------------------------------------
    // Deleting and ordering
    // -----------------------------------------------------------------

    public function test_an_unused_method_is_deleted(): void
    {
        $method = PaymentMethod::factory()->create(['currency_id' => $this->ves->id]);

        $this->actingAs($this->owner())
            ->deleteJson("/api/admin/payment-methods/{$method->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('payment_methods', ['id' => $method->id]);
    }

    /**
     * `orders.payment_method_id` is nullOnDelete, so the delete would go
     * through and quietly erase how those orders were paid.
     */
    public function test_a_method_that_was_used_is_not_deleted(): void
    {
        $method = PaymentMethod::factory()->create(['currency_id' => $this->ves->id]);
        Order::factory()->create(['payment_method_id' => $method->id]);

        $this->actingAs($this->owner())
            ->deleteJson("/api/admin/payment-methods/{$method->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_error');

        $this->assertDatabaseHas('payment_methods', ['id' => $method->id]);
    }

    public function test_it_reorders_the_checkout_list(): void
    {
        $first = PaymentMethod::factory()->create(['currency_id' => $this->ves->id, 'position' => 0]);
        $second = PaymentMethod::factory()->zelle()->create(['currency_id' => $this->usd->id, 'position' => 1]);

        $this->actingAs($this->owner())
            ->postJson('/api/admin/payment-methods/reorder', [
                'payment_methods' => [$second->id, $first->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.0.id', $second->id)
            ->assertJsonPath('data.1.id', $first->id);

        $this->assertSame(0, $second->fresh()->position);
        $this->assertSame(1, $first->fresh()->position);
    }

    public function test_a_partial_reorder_is_rejected(): void
    {
        $first = PaymentMethod::factory()->create(['currency_id' => $this->ves->id]);
        PaymentMethod::factory()->zelle()->create(['currency_id' => $this->usd->id]);

        $this->actingAs($this->owner())
            ->postJson('/api/admin/payment-methods/reorder', ['payment_methods' => [$first->id]])
            ->assertStatus(422)
            ->assertJsonPath(
                'error.fields.payment_methods.0',
                'La lista debe incluir exactamente una vez cada método de pago.',
            );
    }

    // -----------------------------------------------------------------
    // The storefront sees the result
    // -----------------------------------------------------------------

    public function test_deactivating_a_method_takes_it_off_the_storefront(): void
    {
        $method = PaymentMethod::factory()->create(['currency_id' => $this->ves->id]);

        $this->getJson('/api/payment-methods')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->owner())
            ->patchJson("/api/admin/payment-methods/{$method->id}", ['is_active' => false])
            ->assertOk();

        $this->getJson('/api/payment-methods')->assertOk()->assertJsonCount(0, 'data');
    }

    // -----------------------------------------------------------------
    // Permissions
    // -----------------------------------------------------------------

    public function test_staff_cannot_read_or_write_payment_configuration(): void
    {
        $staff = User::factory()->staff()->create();
        $method = PaymentMethod::factory()->create(['currency_id' => $this->ves->id]);

        $this->actingAs($staff)->getJson('/api/admin/payment-methods')->assertStatus(403);
        $this->actingAs($staff)->getJson('/api/admin/payment-method-types')->assertStatus(403);
        $this->actingAs($staff)->getJson("/api/admin/payment-methods/{$method->id}")->assertStatus(403);

        $this->actingAs($staff)
            ->patchJson("/api/admin/payment-methods/{$method->id}", ['label' => 'Mi cuenta'])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');

        $this->actingAs($staff)->deleteJson("/api/admin/payment-methods/{$method->id}")->assertStatus(403);

        $this->actingAs($staff)
            ->postJson('/api/admin/payment-methods/reorder', ['payment_methods' => [$method->id]])
            ->assertStatus(403);

        $this->assertSame('Pago Móvil', $method->fresh()->label);
    }
}
