<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'is_active' => $this->is_active,
            // Lets the panel hide what this account cannot use, without the
            // frontend having to hardcode the role table. The backend stays the
            // authority: this is a UI hint, not the permission itself.
            'permissions' => [
                'manage_settings' => $this->isOwner(),
                'manage_catalog' => $this->isOwner(),
                'manage_users' => $this->isOwner(),
                'manage_orders' => true,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
