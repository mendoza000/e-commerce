<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\UserStoreRequest;
use App\Http\Requests\Api\Admin\UserUpdateRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Owner-only management of admin accounts. The route group already carries
 * `role:owner`; the policy calls below are what enforce the per-record rules
 * (see UserPolicy).
 */
class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(25);

        return UserResource::collection($users);
    }

    public function store(UserStoreRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        // `is_active` is set explicitly rather than left to the column default,
        // so the response carries it without a round-trip to re-read the row.
        $user = User::create([...$request->validated(), 'is_active' => true]);

        return UserResource::make($user)->response()->setStatusCode(201);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return UserResource::make($user)->response();
    }

    public function update(UserUpdateRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $attributes = $request->validated();

        // An absent or blank password means "keep the current one" — the model
        // would otherwise hash the empty string into a valid credential.
        if (blank($attributes['password'] ?? null)) {
            unset($attributes['password']);
        }

        $user->update($attributes);

        // A password change or a demotion has to take effect on the sessions
        // that are already open, not only on the next login.
        if (array_key_exists('password', $attributes) || array_key_exists('role', $attributes)) {
            $user->invalidateSessions();
        }

        return UserResource::make($user)->response();
    }

    /**
     * Accounts are never deleted (see the is_active migration): an operator has
     * to stay resolvable from the order history they wrote.
     *
     * There is deliberately no "last owner" check here — UserPolicy::deactivate
     * explains why one would be unreachable.
     */
    public function deactivate(Request $request, User $user): JsonResponse
    {
        $this->authorize('deactivate', $user);

        $user->deactivate();

        return UserResource::make($user)->response();
    }

    public function activate(Request $request, User $user): JsonResponse
    {
        $this->authorize('activate', $user);

        $user->activate();

        return UserResource::make($user)->response();
    }
}
