<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Session-based admin authentication (Sanctum SPA mode, see
 * docs/decisions.md). There is no token to hand back: the browser holds the
 * session cookie and the panel calls `me` to find out who it is talking as.
 */
class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $request->authenticate();

        // Rotate the session id now that the identity changed, so a session
        // fixated before login cannot be reused after it.
        $request->session()->regenerate();

        return UserResource::make($request->user())->response();
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // `auth:sanctum` resolves through a RequestGuard that caches the user
        // it found, and logging out of the `web` guard does not clear that
        // cache. Under php-fpm the process dies before it matters; under a
        // long-lived worker it would keep serving the identity we just
        // discarded.
        Auth::forgetGuards();

        return response()->json(null, 204);
    }

    public function me(Request $request): JsonResponse
    {
        return UserResource::make($request->user())->response();
    }
}
