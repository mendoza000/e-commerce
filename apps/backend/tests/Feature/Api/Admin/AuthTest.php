<?php

namespace Tests\Feature\Api\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingFromAdminPanel();

        // The login limiter is keyed by email+IP and survives between tests
        // otherwise, making whichever test runs fourth fail for the wrong
        // reason.
        RateLimiter::clear('owner@tienda.test|127.0.0.1');
    }

    public function test_owner_can_log_in(): void
    {
        $owner = User::factory()->owner()->create([
            'email' => 'owner@tienda.test',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email' => 'owner@tienda.test',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $owner->id)
            ->assertJsonPath('data.role', 'owner')
            ->assertJsonPath('data.permissions.manage_settings', true);

        $this->assertAuthenticatedAs($owner);
    }

    public function test_password_is_never_exposed(): void
    {
        User::factory()->owner()->create([
            'email' => 'owner@tienda.test',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email' => 'owner@tienda.test',
            'password' => 'password',
        ]);

        $response->assertOk();
        $this->assertArrayNotHasKey('password', $response->json('data'));
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->owner()->create([
            'email' => 'owner@tienda.test',
            'password' => 'password',
        ]);

        $this->postJson('/api/admin/login', [
            'email' => 'owner@tienda.test',
            'password' => 'incorrecta',
        ])->assertStatus(422)->assertJsonPath('error.code', 'validation_error');

        $this->assertGuest();
    }

    public function test_deactivated_user_cannot_log_in(): void
    {
        User::factory()->owner()->inactive()->create([
            'email' => 'owner@tienda.test',
            'password' => 'password',
        ]);

        $this->postJson('/api/admin/login', [
            'email' => 'owner@tienda.test',
            'password' => 'password',
        ])->assertStatus(422);

        $this->assertGuest();
    }

    public function test_deactivated_user_reason_is_not_disclosed(): void
    {
        User::factory()->owner()->inactive()->create([
            'email' => 'owner@tienda.test',
            'password' => 'password',
        ]);

        $deactivated = $this->postJson('/api/admin/login', [
            'email' => 'owner@tienda.test',
            'password' => 'password',
        ]);

        RateLimiter::clear('fantasma@tienda.test|127.0.0.1');

        $unknown = $this->postJson('/api/admin/login', [
            'email' => 'fantasma@tienda.test',
            'password' => 'password',
        ]);

        // Same answer either way: this endpoint must not double as a way to
        // find out which emails have accounts.
        $this->assertSame(
            $unknown->json('error.fields.email'),
            $deactivated->json('error.fields.email'),
        );
    }

    public function test_login_is_rate_limited_after_five_failures(): void
    {
        User::factory()->owner()->create([
            'email' => 'owner@tienda.test',
            'password' => 'password',
        ]);

        foreach (range(1, 5) as $ignored) {
            $this->postJson('/api/admin/login', [
                'email' => 'owner@tienda.test',
                'password' => 'incorrecta',
            ])->assertStatus(422);
        }

        $response = $this->postJson('/api/admin/login', [
            'email' => 'owner@tienda.test',
            'password' => 'password',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Demasiados intentos', $response->json('error.fields.email.0'));
        $this->assertGuest();
    }

    public function test_me_returns_the_authenticated_admin(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->getJson('/api/admin/me')
            ->assertOk()
            ->assertJsonPath('data.id', $staff->id)
            ->assertJsonPath('data.role', 'staff')
            ->assertJsonPath('data.permissions.manage_settings', false)
            ->assertJsonPath('data.permissions.manage_orders', true);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/admin/me')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    /**
     * Driven through a real login rather than actingAs(), because actingAs()
     * pins the user onto the guard for the whole test and would hide whether
     * the session itself was actually torn down.
     */
    public function test_logout_ends_the_session(): void
    {
        User::factory()->owner()->create([
            'email' => 'owner@tienda.test',
            'password' => 'password',
        ]);

        $this->postJson('/api/admin/login', [
            'email' => 'owner@tienda.test',
            'password' => 'password',
        ])->assertOk();

        $this->getJson('/api/admin/me')->assertOk();

        $this->postJson('/api/admin/logout')->assertNoContent();

        $this->getJson('/api/admin/me')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_a_user_deactivated_mid_session_is_rejected_on_the_next_request(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->getJson('/api/admin/me')->assertOk();

        $staff->update(['is_active' => false]);

        $this->actingAs($staff)
            ->getJson('/api/admin/me')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthenticated');
    }
}
