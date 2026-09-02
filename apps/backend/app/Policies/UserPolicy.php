<?php

namespace App\Policies;

use App\Models\User;

/**
 * Staff accounts are an owner-only concern. The `role:owner` middleware on the
 * route group says the same thing for the group as a whole; this policy is what
 * decides the per-record questions the middleware cannot see — above all, that
 * nobody locks themselves out.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isOwner();
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->isOwner();
    }

    public function create(User $actor): bool
    {
        return $actor->isOwner();
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->isOwner();
    }

    /**
     * Refusing self-deactivation is also what guarantees the store never runs
     * out of owners, and no extra check is needed for that: to deactivate
     * someone else you must be an active owner yourself, which means the target
     * was never the last one. The only way to remove the last owner would be to
     * deactivate yourself — exactly what this refuses.
     *
     * Demotion is the other way to lose an owner, and that one is not covered
     * here because it is self-inflicted too; see UserUpdateRequest::after().
     */
    public function deactivate(User $actor, User $target): bool
    {
        return $actor->isOwner() && ! $actor->is($target);
    }

    public function activate(User $actor, User $target): bool
    {
        return $actor->isOwner();
    }
}
