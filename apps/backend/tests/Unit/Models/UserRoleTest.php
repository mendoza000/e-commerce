<?php

namespace Tests\Unit\Models;

use App\Domain\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_role_helpers(): void
    {
        $owner = User::factory()->create(['role' => Role::Owner]);

        $this->assertTrue($owner->isOwner());
        $this->assertFalse($owner->isStaff());
    }

    public function test_staff_role_helpers(): void
    {
        $staff = User::factory()->create(['role' => Role::Staff]);

        $this->assertFalse($staff->isOwner());
        $this->assertTrue($staff->isStaff());
    }
}
