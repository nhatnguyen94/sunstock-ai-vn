<?php

namespace Tests\Feature\Providers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Exercises the single Gate::before() hook defined in
 * AppServiceProvider::defineGates() — every ability is now resolved from
 * the roles/permissions relation (DB-driven), not a hardcoded per-ability
 * closure. Roles/permissions are injected via setRelation() (no DB), same
 * trick used for hasRole()/hasPermission() elsewhere — see docs/TESTING.md.
 */
class GatesTest extends TestCase
{
    private function userWithPermissions(array $permissionNames): User
    {
        $role = new Role(['name' => 'test-role']);
        $role->setRelation(
            'permissions',
            collect($permissionNames)->map(fn ($name) => new Permission(['name' => $name]))
        );

        $user = new User(['name' => 'Test User', 'email' => 't@example.com']);
        $user->id = 1;
        $user->setRelation('roles', collect([$role]));

        return $user;
    }

    /** Mirrors the exact permission set PermissionSeeder assigns to each of the 4 built-in roles. */
    private function userWithSeededRole(string $roleName): User
    {
        $seededPermissions = [
            Role::ADMIN => ['manage-users', 'manage-roles', 'manage-permissions', 'access-backend', 'view-timeline', 'manage-features'],
            Role::WEBADMIN => ['access-backend', 'view-timeline'],
            Role::ADMIN_SUPPORT => ['access-backend', 'view-timeline', 'manage-features'],
            Role::USER => [],
        ];

        return $this->userWithPermissions($seededPermissions[$roleName]);
    }

    private function userWithNoRoles(): User
    {
        $user = new User(['name' => 'Test User', 'email' => 't@example.com']);
        $user->id = 1;
        $user->setRelation('roles', collect());

        return $user;
    }

    public static function manageUsersProvider(): array
    {
        return [
            'admin allowed' => [Role::ADMIN, true],
            'webadmin denied' => [Role::WEBADMIN, false],
            'adminsupport denied' => [Role::ADMIN_SUPPORT, false],
            'plain user denied' => [Role::USER, false],
        ];
    }

    #[Group('permissions')]
    #[DataProvider('manageUsersProvider')]
    public function test_manage_users_gate(string $roleName, bool $expected): void
    {
        $this->assertSame($expected, Gate::forUser($this->userWithSeededRole($roleName))->allows('manage-users'));
    }

    public static function manageFeaturesProvider(): array
    {
        return [
            'admin allowed' => [Role::ADMIN, true],
            'adminsupport allowed' => [Role::ADMIN_SUPPORT, true],
            'webadmin denied' => [Role::WEBADMIN, false],
            'plain user denied' => [Role::USER, false],
        ];
    }

    #[Group('permissions')]
    #[DataProvider('manageFeaturesProvider')]
    public function test_manage_features_gate(string $roleName, bool $expected): void
    {
        $this->assertSame($expected, Gate::forUser($this->userWithSeededRole($roleName))->allows('manage-features'));
    }

    public static function viewTimelineProvider(): array
    {
        return [
            'admin allowed' => [Role::ADMIN, true],
            'webadmin allowed' => [Role::WEBADMIN, true],
            'adminsupport allowed' => [Role::ADMIN_SUPPORT, true],
            'plain user denied' => [Role::USER, false],
        ];
    }

    #[Group('permissions')]
    #[DataProvider('viewTimelineProvider')]
    public function test_view_timeline_gate(string $roleName, bool $expected): void
    {
        $this->assertSame($expected, Gate::forUser($this->userWithSeededRole($roleName))->allows('view-timeline'));
    }

    public static function accessBackendProvider(): array
    {
        return [
            'admin allowed' => [Role::ADMIN, true],
            'webadmin allowed' => [Role::WEBADMIN, true],
            'adminsupport allowed' => [Role::ADMIN_SUPPORT, true],
            'plain user denied' => [Role::USER, false],
        ];
    }

    #[Group('permissions')]
    #[DataProvider('accessBackendProvider')]
    public function test_access_backend_gate(string $roleName, bool $expected): void
    {
        $this->assertSame($expected, Gate::forUser($this->userWithSeededRole($roleName))->allows('access-backend'));
    }

    #[Group('permissions')]
    public function test_gate_denies_user_with_no_roles_at_all(): void
    {
        $this->assertFalse(Gate::forUser($this->userWithNoRoles())->allows('manage-users'));
    }

    /**
     * The scalability point of this whole feature: a brand-new permission
     * name that has never been Gate::define()'d anywhere in code still
     * resolves correctly purely from the roles/permissions relation — a
     * new role created via Admin > Vai trò with an ad-hoc permission works
     * with can:that-name with zero code changes.
     */
    #[Group('permissions')]
    public function test_gate_allows_arbitrary_permission_name_with_no_code_defined_gate(): void
    {
        $user = $this->userWithPermissions(['manage-newly-invented-thing']);

        $this->assertTrue(Gate::forUser($user)->allows('manage-newly-invented-thing'));
        $this->assertFalse(Gate::forUser($user)->allows('some-other-permission'));
    }
}
