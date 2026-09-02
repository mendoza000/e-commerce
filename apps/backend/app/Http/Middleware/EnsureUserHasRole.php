<?php

namespace App\Http\Middleware;

use App\Domain\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Coarse-grained gate for whole route groups: `role:owner` keeps staff out of
 * the configuration endpoints in one place instead of a check repeated in every
 * controller. Per-record rules (who may touch *which* user) belong in policies.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        $allowed = array_map(
            fn (string $role) => Role::from($role),
            $roles,
        );

        if ($user === null || ! in_array($user->role, $allowed, true)) {
            abort(403, 'No tienes permisos para realizar esta acción.');
        }

        return $next($request);
    }
}
