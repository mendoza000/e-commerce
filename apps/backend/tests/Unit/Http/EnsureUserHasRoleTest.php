<?php

namespace Tests\Unit\Http;

use App\Domain\Enums\Role;
use App\Http\Middleware\EnsureUserHasRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;
use ValueError;

/**
 * The middleware in isolation. Its effect on the real admin routes is covered
 * by tests/Feature/Api/Admin/UserManagementTest; what is checked here are the
 * branches those routes do not exercise — several allowed roles, and no user
 * at all.
 */
class EnsureUserHasRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lets_through_a_user_with_the_required_role(): void
    {
        $response = $this->dispatch(User::factory()->owner()->create(), 'owner');

        $this->assertSame('ok', $response->getContent());
    }

    public function test_it_rejects_a_user_without_the_required_role(): void
    {
        $this->assertRejects(fn () => $this->dispatch(User::factory()->staff()->create(), 'owner'));
    }

    public function test_any_of_several_listed_roles_is_enough(): void
    {
        foreach ([Role::Owner, Role::Staff] as $role) {
            $response = $this->dispatch(User::factory()->create(['role' => $role]), 'owner', 'staff');

            $this->assertSame('ok', $response->getContent());
        }
    }

    /**
     * The route group always sits behind `auth`, so this should be unreachable
     * — but a middleware that reads `$request->user()` must not fail open if
     * it ever is reordered.
     */
    public function test_it_rejects_a_request_with_no_authenticated_user(): void
    {
        $this->assertRejects(fn () => $this->dispatch(null, 'owner'));
    }

    public function test_an_unknown_role_name_blows_up_rather_than_letting_anyone_in(): void
    {
        $this->expectException(ValueError::class);

        $this->dispatch(User::factory()->owner()->create(), 'superadmin');
    }

    /**
     * The 403 lives in the HTTP status, not in the exception code, so it has to
     * be caught rather than asserted with expectExceptionCode().
     */
    private function assertRejects(callable $call): void
    {
        try {
            $call();
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());

            return;
        }

        $this->fail('Se esperaba un 403 y el middleware dejó pasar el request.');
    }

    private function dispatch(?User $user, string ...$roles): Response
    {
        $request = Request::create('/api/admin/users');
        $request->setUserResolver(fn () => $user);

        return (new EnsureUserHasRole)->handle(
            $request,
            fn () => new Response('ok'),
            ...$roles,
        );
    }
}
