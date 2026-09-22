<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Domain\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
    }

    // ---------------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------------

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    #[Scope]
    protected function owners(Builder $query): void
    {
        $query->where('role', Role::Owner);
    }

    // ---------------------------------------------------------------------
    // Roles
    // ---------------------------------------------------------------------

    public function isOwner(): bool
    {
        return $this->role === Role::Owner;
    }

    public function isStaff(): bool
    {
        return $this->role === Role::Staff;
    }

    public function hasRole(Role $role): bool
    {
        return $this->role === $role;
    }

    // ---------------------------------------------------------------------
    // Activation
    // ---------------------------------------------------------------------

    /**
     * Deactivating has to log the account out everywhere, not just deny the
     * next login: an operator who is dismissed mid-shift must lose the session
     * already open in their browser.
     */
    public function deactivate(): void
    {
        $this->update(['is_active' => false]);

        $this->invalidateSessions();
    }

    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }

    /**
     * Drops every session this user has open. Only the database session driver
     * keeps sessions where we can reach them; with any other driver the
     * `active` middleware is what stops a deactivated user on their next
     * request, one round-trip later.
     */
    public function invalidateSessions(): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $this->getKey())
            ->delete();
    }

    /**
     * Whether the store would be left with nobody able to reach its
     * configuration if this account went away. Guards both deactivation and
     * demotion to staff.
     */
    public function isLastActiveOwner(): bool
    {
        if (! $this->isOwner() || ! $this->is_active) {
            return false;
        }

        return static::query()->active()->owners()->whereKeyNot($this->getKey())->doesntExist();
    }
}
