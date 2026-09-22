<?php

namespace Tests\Feature\Api\Admin;

use App\Domain\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingFromAdminPanel();
    }

    public function test_owner_can_list_users(): void
    {
        $owner = User::factory()->owner()->create();
        User::factory()->staff()->count(2)->create();

        $this->actingAs($owner)
            ->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_owner_can_create_a_staff_account(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->postJson('/api/admin/users', [
                'name' => 'Operador Uno',
                'email' => 'operador@tienda.test',
                'password' => 'contrasena-larga-1',
                'password_confirmation' => 'contrasena-larga-1',
                'role' => Role::Staff->value,
            ])
            ->assertCreated()
            ->assertJsonPath('data.role', 'staff')
            ->assertJsonPath('data.is_active', true);

        $created = User::query()->where('email', 'operador@tienda.test')->firstOrFail();

        $this->assertTrue(Hash::check('contrasena-larga-1', $created->password));
    }

    public function test_owner_can_update_a_user_without_touching_the_password(): void
    {
        $owner = User::factory()->owner()->create();
        $staff = User::factory()->staff()->create(['password' => 'original-larga-1']);

        $this->actingAs($owner)
            ->patchJson("/api/admin/users/{$staff->id}", ['name' => 'Nombre Nuevo'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nombre Nuevo');

        $this->assertTrue(Hash::check('original-larga-1', $staff->fresh()->password));
    }

    public function test_owner_can_deactivate_and_reactivate_a_staff_account(): void
    {
        $owner = User::factory()->owner()->create();
        $staff = User::factory()->staff()->create();

        $this->actingAs($owner)
            ->postJson("/api/admin/users/{$staff->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertFalse($staff->fresh()->is_active);

        $this->actingAs($owner)
            ->postJson("/api/admin/users/{$staff->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->assertTrue($staff->fresh()->is_active);
    }

    public function test_deactivating_drops_the_stored_sessions_of_that_user(): void
    {
        config(['session.driver' => 'database']);

        $owner = User::factory()->owner()->create();
        $staff = User::factory()->staff()->create();

        $this->insertSession('sesion-del-staff', $staff->id);
        $this->insertSession('sesion-de-otro', $owner->id);

        $this->actingAs($owner)
            ->postJson("/api/admin/users/{$staff->id}/deactivate")
            ->assertOk();

        $this->assertDatabaseMissing('sessions', ['id' => 'sesion-del-staff']);
        $this->assertDatabaseHas('sessions', ['id' => 'sesion-de-otro']);
    }

    public function test_owner_cannot_deactivate_themselves(): void
    {
        $owner = User::factory()->owner()->create();
        User::factory()->owner()->create();

        $this->actingAs($owner)
            ->postJson("/api/admin/users/{$owner->id}/deactivate")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');

        $this->assertTrue($owner->fresh()->is_active);
    }

    /**
     * The store must never be left without an owner. Nothing checks that
     * explicitly: refusing self-deactivation is enough, because deactivating
     * anyone else requires being an active owner yourself. This walks the store
     * down to a single owner and shows there is no move left that removes them.
     */
    public function test_the_store_cannot_be_left_without_an_active_owner(): void
    {
        $owner = User::factory()->owner()->create();
        $otherOwner = User::factory()->owner()->create();

        // Down to one active owner.
        $this->actingAs($owner)
            ->postJson("/api/admin/users/{$otherOwner->id}/deactivate")
            ->assertOk();

        // Deactivating themselves: refused by the policy.
        $this->actingAs($owner)
            ->postJson("/api/admin/users/{$owner->id}/deactivate")
            ->assertStatus(403);

        // Demoting themselves: refused by validation.
        $this->actingAs($owner)
            ->patchJson("/api/admin/users/{$owner->id}", ['role' => Role::Staff->value])
            ->assertStatus(422);

        $owner->refresh();
        $this->assertTrue($owner->is_active);
        $this->assertTrue($owner->isOwner());
    }

    public function test_the_last_active_owner_cannot_be_demoted_to_staff(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->patchJson("/api/admin/users/{$owner->id}", ['role' => Role::Staff->value])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_error');

        $this->assertTrue($owner->fresh()->isOwner());
    }

    public function test_an_owner_can_be_demoted_while_another_active_owner_remains(): void
    {
        $owner = User::factory()->owner()->create();
        $otherOwner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->patchJson("/api/admin/users/{$otherOwner->id}", ['role' => Role::Staff->value])
            ->assertOk()
            ->assertJsonPath('data.role', 'staff');
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $owner = User::factory()->owner()->create();
        $staff = User::factory()->staff()->create();

        $this->actingAs($owner)
            ->postJson('/api/admin/users', [
                'name' => 'Duplicado',
                'email' => $staff->email,
                'password' => 'contrasena-larga-1',
                'password_confirmation' => 'contrasena-larga-1',
                'role' => Role::Staff->value,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_error');
    }

    #[DataProvider('ownerOnlyEndpoints')]
    public function test_staff_cannot_reach_owner_only_endpoints(string $method, string $path): void
    {
        $staff = User::factory()->staff()->create();
        $target = User::factory()->staff()->create();

        $path = str_replace('{id}', (string) $target->id, $path);

        $this->actingAs($staff)
            ->json($method, $path)
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function ownerOnlyEndpoints(): array
    {
        return [
            'listar usuarios' => ['GET', '/api/admin/users'],
            'crear usuario' => ['POST', '/api/admin/users'],
            'ver usuario' => ['GET', '/api/admin/users/{id}'],
            'editar usuario' => ['PATCH', '/api/admin/users/{id}'],
            'desactivar usuario' => ['POST', '/api/admin/users/{id}/deactivate'],
            'activar usuario' => ['POST', '/api/admin/users/{id}/activate'],
        ];
    }

    public function test_guests_cannot_reach_user_management(): void
    {
        $this->getJson('/api/admin/users')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    private function insertSession(string $id, int $userId): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => base64_encode('x'),
            'last_activity' => now()->getTimestamp(),
        ]);
    }
}
