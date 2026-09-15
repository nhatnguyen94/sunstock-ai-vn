<?php

namespace Tests\Feature\Backend\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Real end-to-end DB round-trip (RefreshDatabase) — genuinely needed here:
 * the whole point of RoleController is syncing the permission_role pivot
 * table and enforcing unique/exists validation against real rows, and the
 * `can:manage-roles` route middleware must resolve through the real
 * Gate::before() -> User::hasPermission() -> DB chain. See docs/TESTING.md.
 */
class RoleControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The role name must be one of the 3 hardcoded backend-access roles
     * (AdminAccess middleware / User::canAccessBackend() still checks role
     * NAME, unchanged by this feature — see docs/RBAC.md) so the request
     * actually reaches the `can:manage-roles` gate under test, rather than
     * being redirected earlier by AdminAccess for an unrelated reason.
     */
    private function actingAsUserWithPermissions(array $permissionNames): User
    {
        $role = Role::create(['name' => Role::WEBADMIN, 'display_name' => 'Web Admin']);

        if (!empty($permissionNames)) {
            $ids = collect($permissionNames)->map(
                fn ($name) => Permission::create(['name' => $name, 'display_name' => $name])->id
            );
            $role->permissions()->sync($ids);
        }

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $this->actingAs($user);

        return $user;
    }

    #[Group('permissions')]
    public function test_user_without_manage_roles_permission_is_forbidden(): void
    {
        $this->actingAsUserWithPermissions([]);

        $response = $this->get('/admin/roles');

        $response->assertForbidden();
    }

    #[Group('permissions')]
    public function test_index_lists_roles_for_permitted_user(): void
    {
        $this->actingAsUserWithPermissions(['manage-roles']);
        Role::create(['name' => 'moderator', 'display_name' => 'Moderator']);

        $response = $this->get('/admin/roles');

        $response->assertOk();
        $response->assertSee('Moderator');
    }

    #[Group('permissions')]
    public function test_store_creates_role_with_permissions(): void
    {
        $this->actingAsUserWithPermissions(['manage-roles']);
        $perm = Permission::create(['name' => 'manage-alerts', 'display_name' => 'Manage Alerts']);

        $response = $this->post('/admin/roles', [
            'name' => 'alert-manager',
            'display_name' => 'Alert Manager',
            'permissions' => [$perm->id],
        ]);

        $response->assertRedirect(route('admin.roles.index'));
        $this->assertDatabaseHas('roles', ['name' => 'alert-manager']);
        $role = Role::where('name', 'alert-manager')->first();
        $this->assertTrue($role->hasPermission('manage-alerts'));
    }

    #[Group('permissions')]
    public function test_update_syncs_permissions(): void
    {
        $this->actingAsUserWithPermissions(['manage-roles']);
        $role = Role::create(['name' => 'moderator', 'display_name' => 'Moderator']);
        $oldPerm = Permission::create(['name' => 'old-perm', 'display_name' => 'Old']);
        $newPerm = Permission::create(['name' => 'new-perm', 'display_name' => 'New']);
        $role->permissions()->sync([$oldPerm->id]);

        $response = $this->put("/admin/roles/{$role->id}", [
            'name' => 'moderator',
            'display_name' => 'Moderator',
            'permissions' => [$newPerm->id],
        ]);

        $response->assertRedirect(route('admin.roles.index'));
        $role->refresh();
        $this->assertFalse($role->hasPermission('old-perm'));
        $this->assertTrue($role->hasPermission('new-perm'));
    }

    #[Group('permissions')]
    public function test_destroy_deletes_a_custom_role(): void
    {
        $this->actingAsUserWithPermissions(['manage-roles']);
        $role = Role::create(['name' => 'moderator', 'display_name' => 'Moderator']);

        $response = $this->delete("/admin/roles/{$role->id}");

        $response->assertRedirect(route('admin.roles.index'));
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    #[Group('permissions')]
    public function test_cannot_destroy_a_system_role(): void
    {
        $this->actingAsUserWithPermissions(['manage-roles']);
        $adminRole = Role::create(['name' => Role::ADMIN, 'display_name' => 'Admin']);

        $response = $this->delete("/admin/roles/{$adminRole->id}");

        $response->assertRedirect(route('admin.roles.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('roles', ['id' => $adminRole->id]);
    }

    #[Group('permissions')]
    public function test_cannot_destroy_a_role_that_still_has_users_assigned(): void
    {
        $this->actingAsUserWithPermissions(['manage-roles']);
        $role = Role::create(['name' => 'moderator', 'display_name' => 'Moderator']);
        $otherUser = User::factory()->create();
        $otherUser->roles()->attach($role->id);

        $response = $this->delete("/admin/roles/{$role->id}");

        $response->assertRedirect(route('admin.roles.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }
}
