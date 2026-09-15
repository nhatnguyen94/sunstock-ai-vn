<?php

namespace Tests\Feature\Backend\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Real end-to-end DB round-trip (RefreshDatabase) — same reasoning as
 * RoleControllerTest: unique/exists validation and the `can:manage-
 * permissions` route middleware both need real rows. See docs/TESTING.md.
 */
class PermissionControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @see RoleControllerTest::actingAsUserWithPermissions() for why the role name matters. */
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
    public function test_user_without_manage_permissions_permission_is_forbidden(): void
    {
        $this->actingAsUserWithPermissions([]);

        $response = $this->get('/admin/permissions');

        $response->assertForbidden();
    }

    #[Group('permissions')]
    public function test_index_lists_permissions_for_permitted_user(): void
    {
        $this->actingAsUserWithPermissions(['manage-permissions']);
        Permission::create(['name' => 'manage-alerts', 'display_name' => 'Quản lý Cảnh báo']);

        $response = $this->get('/admin/permissions');

        $response->assertOk();
        $response->assertSee('Quản lý Cảnh báo');
    }

    #[Group('permissions')]
    public function test_store_creates_permission(): void
    {
        $this->actingAsUserWithPermissions(['manage-permissions']);

        $response = $this->post('/admin/permissions', [
            'name' => 'manage-alerts',
            'display_name' => 'Quản lý Cảnh báo',
            'group' => 'Tính năng',
        ]);

        $response->assertRedirect(route('admin.permissions.index'));
        $this->assertDatabaseHas('permissions', ['name' => 'manage-alerts', 'group' => 'Tính năng']);
    }

    #[Group('permissions')]
    public function test_store_rejects_duplicate_name(): void
    {
        $this->actingAsUserWithPermissions(['manage-permissions']);
        Permission::create(['name' => 'manage-alerts', 'display_name' => 'Existing']);

        $response = $this->post('/admin/permissions', [
            'name' => 'manage-alerts',
            'display_name' => 'Duplicate',
        ]);

        $response->assertSessionHasErrors('name');
    }

    #[Group('permissions')]
    public function test_update_permission(): void
    {
        $this->actingAsUserWithPermissions(['manage-permissions']);
        $permission = Permission::create(['name' => 'manage-alerts', 'display_name' => 'Old Name']);

        $response = $this->put("/admin/permissions/{$permission->id}", [
            'name' => 'manage-alerts',
            'display_name' => 'New Name',
        ]);

        $response->assertRedirect(route('admin.permissions.index'));
        $this->assertDatabaseHas('permissions', ['id' => $permission->id, 'display_name' => 'New Name']);
    }

    #[Group('permissions')]
    public function test_destroy_deletes_a_custom_permission(): void
    {
        $this->actingAsUserWithPermissions(['manage-permissions']);
        $permission = Permission::create(['name' => 'manage-alerts', 'display_name' => 'Quản lý Cảnh báo']);

        $response = $this->delete("/admin/permissions/{$permission->id}");

        $response->assertRedirect(route('admin.permissions.index'));
        $this->assertDatabaseMissing('permissions', ['id' => $permission->id]);
    }

    #[Group('permissions')]
    public function test_cannot_destroy_a_core_permission(): void
    {
        $this->actingAsUserWithPermissions(['manage-permissions']);
        $corePermission = Permission::where('name', 'manage-permissions')->first();

        $response = $this->delete("/admin/permissions/{$corePermission->id}");

        $response->assertRedirect(route('admin.permissions.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('permissions', ['id' => $corePermission->id]);
    }
}
