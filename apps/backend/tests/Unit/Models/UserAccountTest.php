<?php

namespace Tests\Unit\Models;

use App\Domain\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Activation and the "there is always an owner" invariant, tested on the model
 * itself. The HTTP side of the same rules lives in
 * tests/Feature/Api/Admin/UserManagementTest.
 */
class UserAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_role_matches_the_assigned_role(): void
    {
        $owner = User::factory()->owner()->create();

        $this->assertTrue($owner->hasRole(Role::Owner));
        $this->assertFalse($owner->hasRole(Role::Staff));
    }

    public function test_accounts_are_active_by_default(): void
    {
        $this->assertTrue(User::factory()->create()->is_active);
    }

    public function test_deactivate_and_activate_flip_the_flag(): void
    {
        $user = User::factory()->create();

        $user->deactivate();
        $this->assertFalse($user->fresh()->is_active);

        $user->activate();
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_the_active_scope_excludes_deactivated_accounts(): void
    {
        User::factory()->count(2)->create();
        User::factory()->inactive()->create();

        $this->assertSame(2, User::query()->active()->count());
    }

    public function test_the_owners_scope_excludes_staff(): void
    {
        User::factory()->owner()->create();
        User::factory()->staff()->count(3)->create();

        $this->assertSame(1, User::query()->owners()->count());
    }

    // -----------------------------------------------------------------
    // isLastActiveOwner — the guard behind the lockout invariant
    // -----------------------------------------------------------------

    public function test_a_sole_owner_is_the_last_active_owner(): void
    {
        $owner = User::factory()->owner()->create();
        User::factory()->staff()->count(2)->create();

        $this->assertTrue($owner->isLastActiveOwner());
    }

    public function test_an_owner_is_not_the_last_one_when_another_owner_is_active(): void
    {
        $owner = User::factory()->owner()->create();
        User::factory()->owner()->create();

        $this->assertFalse($owner->isLastActiveOwner());
    }

    /**
     * A deactivated owner does not count as company: the survivor is still the
     * last one that can reach the configuration.
     */
    public function test_a_deactivated_owner_does_not_count_as_another_active_owner(): void
    {
        $owner = User::factory()->owner()->create();
        User::factory()->owner()->inactive()->create();

        $this->assertTrue($owner->isLastActiveOwner());
    }

    public function test_staff_is_never_the_last_active_owner(): void
    {
        $staff = User::factory()->staff()->create();

        $this->assertFalse($staff->isLastActiveOwner());
    }

    public function test_an_already_deactivated_owner_is_not_the_last_active_owner(): void
    {
        $owner = User::factory()->owner()->inactive()->create();

        $this->assertFalse($owner->isLastActiveOwner());
    }

    // -----------------------------------------------------------------
    // Session invalidation
    // -----------------------------------------------------------------

    public function test_deactivating_removes_only_that_users_sessions(): void
    {
        config(['session.driver' => 'database']);

        $target = User::factory()->create();
        $bystander = User::factory()->create();

        $this->storeSession('de-la-cuenta', $target->id);
        $this->storeSession('de-otra-cuenta', $bystander->id);
        $this->storeSession('de-un-invitado', null);

        $target->deactivate();

        $this->assertDatabaseMissing('sessions', ['id' => 'de-la-cuenta']);
        $this->assertDatabaseHas('sessions', ['id' => 'de-otra-cuenta']);
        $this->assertDatabaseHas('sessions', ['id' => 'de-un-invitado']);
    }

    /**
     * Only the database driver keeps sessions where we can reach them. With any
     * other driver this is a silent no-op — the `active` middleware is what
     * stops the account instead, one request later.
     */
    public function test_session_invalidation_is_a_no_op_on_a_non_database_driver(): void
    {
        config(['session.driver' => 'array']);

        $user = User::factory()->create();
        $this->storeSession('sobrevive', $user->id);

        $user->invalidateSessions();

        $this->assertDatabaseHas('sessions', ['id' => 'sobrevive']);
    }

    public function test_reactivating_does_not_touch_sessions(): void
    {
        config(['session.driver' => 'database']);

        $user = User::factory()->inactive()->create();
        $this->storeSession('intacta', $user->id);

        $user->activate();

        $this->assertDatabaseHas('sessions', ['id' => 'intacta']);
    }

    private function storeSession(string $id, ?int $userId): void
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
