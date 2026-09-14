<?php

namespace Tests\Unit\Models;

use App\Models\Role;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class RoleTest extends TestCase
{
    #[Group('auth')]
    public function test_backend_roles_can_access_backend(): void
    {
        foreach ([Role::ADMIN, Role::WEBADMIN, Role::ADMIN_SUPPORT] as $name) {
            $role = new Role(['name' => $name]);
            $this->assertTrue($role->canAccessBackend(), "{$name} should be a backend role");
        }
    }

    #[Group('auth')]
    public function test_plain_user_role_cannot_access_backend(): void
    {
        $role = new Role(['name' => Role::USER]);
        $this->assertFalse($role->canAccessBackend());
    }

    #[Group('auth')]
    public function test_unknown_role_name_cannot_access_backend(): void
    {
        // Defensive: an unrecognized role name must never default to backend access.
        $role = new Role(['name' => 'something-made-up']);
        $this->assertFalse($role->canAccessBackend());
    }

    #[Group('auth')]
    public function test_get_backend_roles_lists_exactly_the_three_backend_roles(): void
    {
        $this->assertSame(
            [Role::ADMIN, Role::WEBADMIN, Role::ADMIN_SUPPORT],
            Role::getBackendRoles()
        );
    }
}
