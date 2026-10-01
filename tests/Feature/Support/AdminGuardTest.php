<?php

namespace Tests\Feature\Support;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\AdminGuard;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/** The rules that stop the administration area from being locked or hijacked from the inside. */
class AdminGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function user(string $role, array $state = []): User
    {
        $u = User::factory()->create($state);
        $u->assignRole($role);

        return $u->fresh();
    }

    #[Group('authzSecurity')]
    public function test_constants_cover_exactly_the_roles_and_permissions_the_code_depends_on(): void
    {
        $this->assertEqualsCanonicalizing(['admin', 'webadmin', 'adminsupport', 'user'], AdminGuard::SYSTEM_ROLES);
        $this->assertEqualsCanonicalizing(['manage-roles', 'manage-permissions'], AdminGuard::CORE_PERMISSIONS);
    }

    #[Group('authzSecurity')]
    public function test_an_effective_admin_must_have_the_role_an_active_status_and_a_confirmed_email(): void
    {
        $this->assertTrue(AdminGuard::isEffectiveAdmin($this->user(Role::ADMIN)));
        $this->assertFalse(AdminGuard::isEffectiveAdmin($this->user(Role::WEBADMIN)));
        $this->assertFalse(AdminGuard::isEffectiveAdmin($this->user(Role::ADMIN, ['status' => User::STATUS_BLOCKED])));
        $this->assertFalse(AdminGuard::isEffectiveAdmin($this->user(Role::ADMIN, ['status' => User::STATUS_INACTIVE])));
        $this->assertFalse(AdminGuard::isEffectiveAdmin($this->user(Role::ADMIN, ['status' => User::STATUS_PENDING, 'email_verified_at' => null])));
    }

    #[Group('authzSecurity')]
    public function test_other_effective_admin_exists_ignores_blocked_and_unverified_admins(): void
    {
        $sole = $this->user(Role::ADMIN);
        $this->user(Role::ADMIN, ['status' => User::STATUS_BLOCKED]);
        $this->user(Role::ADMIN, ['status' => User::STATUS_PENDING, 'email_verified_at' => null]);
        $this->user(Role::WEBADMIN);

        $this->assertFalse(AdminGuard::otherEffectiveAdminExists($sole));

        $this->user(Role::ADMIN);
        $this->assertTrue(AdminGuard::otherEffectiveAdminExists($sole));
    }

    #[Group('authzSecurity')]
    public function test_an_actor_cannot_remove_their_own_admin_role_or_switch_themselves_off(): void
    {
        $a = $this->user(Role::ADMIN);
        $this->user(Role::ADMIN);   // another admin exists, so "last admin" is not what blocks this

        $this->assertStringContainsString('tự gỡ vai trò admin', AdminGuard::userChangeProblem($a, $a, [Role::USER], User::STATUS_ACTIVE));
        foreach ([User::STATUS_BLOCKED, User::STATUS_INACTIVE, User::STATUS_PENDING] as $status) {
            $this->assertStringContainsString('tự khoá', AdminGuard::userChangeProblem($a, $a, [Role::ADMIN], $status), "status $status");
        }
        $this->assertNull(AdminGuard::userChangeProblem($a, $a, [Role::ADMIN, Role::USER], User::STATUS_ACTIVE));
    }

    #[Group('authzSecurity')]
    public function test_the_last_effective_admin_cannot_be_demoted_blocked_or_deleted_by_someone_else(): void
    {
        $sole = $this->user(Role::ADMIN);
        $other = $this->user(Role::WEBADMIN);   // not an admin: cannot make this a "second admin"

        $this->assertStringContainsString('admin cuối cùng', AdminGuard::userChangeProblem($other, $sole, [Role::USER], User::STATUS_ACTIVE));
        $this->assertStringContainsString('admin cuối cùng', AdminGuard::userChangeProblem($other, $sole, [Role::ADMIN], User::STATUS_BLOCKED));
        $this->assertStringContainsString('admin cuối cùng', AdminGuard::deleteProblem($other, $sole));
        $this->assertNull(AdminGuard::userChangeProblem($other, $sole, [Role::ADMIN], User::STATUS_ACTIVE));   // no real change

        $second = $this->user(Role::ADMIN);
        $this->assertNull(AdminGuard::userChangeProblem($second, $sole, [Role::USER], User::STATUS_ACTIVE));
        $this->assertNull(AdminGuard::deleteProblem($second, $sole));
    }

    #[Group('authzSecurity')]
    public function test_a_blocked_admin_does_not_count_so_the_remaining_one_is_protected(): void
    {
        $sole = $this->user(Role::ADMIN);
        $this->user(Role::ADMIN, ['status' => User::STATUS_BLOCKED]);
        $actor = $this->user(Role::WEBADMIN);

        $this->assertNotNull(AdminGuard::userChangeProblem($actor, $sole, [Role::USER], User::STATUS_ACTIVE));
    }

    #[Group('authzSecurity')]
    public function test_delete_problem_blocks_self_deletion(): void
    {
        $a = $this->user(Role::ADMIN);
        $this->user(Role::ADMIN);

        $this->assertStringContainsString('xóa chính mình', AdminGuard::deleteProblem($a, $a));
        $this->assertNull(AdminGuard::deleteProblem($a, $this->user(Role::USER)));
    }

    #[Group('authzSecurity')]
    public function test_ordinary_accounts_can_be_changed_freely(): void
    {
        $admin = $this->user(Role::ADMIN);
        $victim = $this->user(Role::USER);

        foreach ([User::STATUS_BLOCKED, User::STATUS_INACTIVE, User::STATUS_PENDING, User::STATUS_ACTIVE] as $status) {
            $this->assertNull(AdminGuard::userChangeProblem($admin, $victim, [Role::USER, Role::WEBADMIN], $status));
        }
        $this->assertNull(AdminGuard::deleteProblem($admin, $victim));
    }

    #[Group('authzSecurity')]
    public function test_role_problem_protects_system_role_names_and_the_admin_roles_core_permissions(): void
    {
        $perm = fn (string $n) => Permission::where('name', $n)->firstOrFail()->id;
        $admin = Role::where('name', Role::ADMIN)->first();
        $custom = Role::create(['name' => 'moderator', 'display_name' => 'Mod']);

        foreach (Role::whereIn('name', AdminGuard::SYSTEM_ROLES)->get() as $role) {
            $this->assertStringContainsString('đổi tên vai trò hệ thống', AdminGuard::roleProblem($role, $role->name.'-x', [$perm('manage-roles'), $perm('manage-permissions')]));
        }

        $this->assertNull(AdminGuard::roleProblem($custom, 'moderator-2', []));
        $this->assertNull(AdminGuard::roleProblem($admin, 'admin', [$perm('manage-roles'), $perm('manage-permissions'), $perm('manage-users')]));
        $this->assertStringContainsString('manage-permissions', AdminGuard::roleProblem($admin, 'admin', [$perm('manage-roles')]));
        $this->assertStringContainsString('manage-roles', AdminGuard::roleProblem($admin, 'admin', []));
        $this->assertNull(AdminGuard::roleProblem(Role::where('name', Role::WEBADMIN)->first(), 'webadmin', []));   // only the admin role needs the core ones
    }

    #[Group('authzSecurity')]
    public function test_permission_problem_only_locks_the_core_permission_names(): void
    {
        $core = Permission::where('name', 'manage-roles')->first();
        $other = Permission::where('name', 'view-timeline')->first();

        $this->assertStringContainsString('quyền lõi', AdminGuard::permissionProblem($core, 'renamed'));
        $this->assertNull(AdminGuard::permissionProblem($core, 'manage-roles'));
        $this->assertNull(AdminGuard::permissionProblem($other, 'view-timeline-2'));
    }
}
