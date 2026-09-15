<?php

namespace Tests\Unit\Models;

use App\Models\Permission;
use App\Models\Role;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class RoleTest extends TestCase
{
    private function roleWithPermissions(array $permissionNames): Role
    {
        $role = new Role(['name' => 'test-role']);
        $role->setRelation(
            'permissions',
            collect($permissionNames)->map(fn ($name) => new Permission(['name' => $name]))
        );

        return $role;
    }

    #[Group('permissions')]
    public function test_has_permission_true_when_role_has_it(): void
    {
        $this->assertTrue($this->roleWithPermissions(['manage-users'])->hasPermission('manage-users'));
    }

    #[Group('permissions')]
    public function test_has_permission_false_when_role_does_not_have_it(): void
    {
        $this->assertFalse($this->roleWithPermissions(['view-timeline'])->hasPermission('manage-users'));
    }

    #[Group('permissions')]
    public function test_has_permission_false_when_role_has_no_permissions_at_all(): void
    {
        $this->assertFalse($this->roleWithPermissions([])->hasPermission('manage-users'));
    }

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
