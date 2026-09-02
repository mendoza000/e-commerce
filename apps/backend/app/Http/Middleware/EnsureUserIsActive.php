<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Second line of defence behind User::deactivate(), which already drops the
 * account's stored sessions. This catches the cases that cannot: a session
 * driver we cannot reach into, or a request already in flight when the account
 * was switched off.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->is_active) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            abort(401, 'Tu cuenta fue desactivada.');
        }

        return $next($request);
    }
}
