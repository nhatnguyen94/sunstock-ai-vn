<?php

namespace Tests\Unit\Models;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit tests: no Laravel app booted, no database touched.
 * Roles are injected via setRelation() rather than the pivot table —
 * this only works because hasRole()/hasAnyRole() were changed to read
 * the `roles` relation property instead of always issuing a fresh
 * roles()->where(...) query. See tests/README (docs/TESTING.md).
 */
class UserTest extends TestCase
{
    private function userWithRoles(array $roleNames): User
    {
        $user = new User(['name' => 'Test User', 'email' => 't@example.com']);
        $user->setRelation('roles', collect($roleNames)->map(fn ($name) => new Role(['name' => $name])));

        return $user;
    }

    #[Group('auth')]
    public function test_has_role_true_when_user_has_it(): void
    {
        $this->assertTrue($this->userWithRoles([Role::ADMIN])->hasRole(Role::ADMIN));
    }

    #[Group('auth')]
    public function test_has_role_false_when_user_does_not_have_it(): void
    {
        $this->assertFalse($this->userWithRoles([Role::USER])->hasRole(Role::ADMIN));
    }

    #[Group('auth')]
    public function test_has_role_false_when_user_has_no_roles_at_all(): void
    {
        $this->assertFalse($this->userWithRoles([])->hasRole(Role::ADMIN));
    }

    #[Group('auth')]
    public function test_has_any_role_true_when_one_of_several_matches(): void
    {
        $user = $this->userWithRoles([Role::WEBADMIN]);

        $this->assertTrue($user->hasAnyRole([Role::ADMIN, Role::WEBADMIN, Role::ADMIN_SUPPORT]));
    }

    #[Group('auth')]
    public function test_has_any_role_false_when_none_match(): void
    {
        $user = $this->userWithRoles([Role::USER]);

        $this->assertFalse($user->hasAnyRole([Role::ADMIN, Role::WEBADMIN, Role::ADMIN_SUPPORT]));
    }

    #[Group('auth')]
    public function test_can_access_backend_true_for_each_backend_role(): void
    {
        foreach ([Role::ADMIN, Role::WEBADMIN, Role::ADMIN_SUPPORT] as $role) {
            $this->assertTrue(
                $this->userWithRoles([$role])->canAccessBackend(),
                "Role {$role} should be able to access the backend"
            );
        }
    }

    #[Group('auth')]
    public function test_can_access_backend_false_for_plain_user_role(): void
    {
        $this->assertFalse($this->userWithRoles([Role::USER])->canAccessBackend());
    }

    #[Group('auth')]
    public function test_can_access_backend_false_for_user_with_no_roles(): void
    {
        // A user who somehow has zero role rows (e.g. mid-migration data issue)
        // must NOT default to backend access.
        $this->assertFalse($this->userWithRoles([])->canAccessBackend());
    }

    #[Group('auth')]
    public function test_get_role_names_returns_all_assigned_role_names(): void
    {
        $user = $this->userWithRoles([Role::ADMIN, Role::WEBADMIN]);

        $this->assertSame([Role::ADMIN, Role::WEBADMIN], $user->getRoleNames());
    }

    private function userWithRolePermissions(array $permissionNames): User
    {
        $role = new Role(['name' => 'test-role']);
        $role->setRelation(
            'permissions',
            collect($permissionNames)->map(fn ($name) => new Permission(['name' => $name]))
        );

        $user = new User(['name' => 'Test User', 'email' => 't@example.com']);
        $user->setRelation('roles', collect([$role]));

        return $user;
    }

    #[Group('permissions')]
    public function test_has_permission_true_when_one_of_the_users_roles_has_it(): void
    {
        $this->assertTrue($this->userWithRolePermissions(['manage-users'])->hasPermission('manage-users'));
    }

    #[Group('permissions')]
    public function test_has_permission_false_when_no_role_has_it(): void
    {
        $this->assertFalse($this->userWithRolePermissions(['view-timeline'])->hasPermission('manage-users'));
    }

    #[Group('permissions')]
    public function test_has_permission_false_when_user_has_no_roles_at_all(): void
    {
        $user = new User(['name' => 'Test User', 'email' => 't@example.com']);
        $user->setRelation('roles', collect());

        $this->assertFalse($user->hasPermission('manage-users'));
    }
}
