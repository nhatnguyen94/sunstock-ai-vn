<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Khởi tạo đúng bộ permission mà Gate::before() (AppServiceProvider)
     * từng hardcode, và gán lại cho 4 role sẵn có sao cho hành vi hệ thống
     * giữ nguyên 100% so với trước khi có bảng permissions. Từ giờ, mọi
     * permission/role mới đều tạo qua Admin > Vai trò & Quyền hạn, không
     * cần sửa seeder này nữa.
     */
    public function run(): void
    {
        $permissions = [
            ['name' => 'manage-users', 'display_name' => 'Quản lý Users', 'group' => 'Hệ thống'],
            ['name' => 'manage-roles', 'display_name' => 'Quản lý Vai trò', 'group' => 'Hệ thống'],
            ['name' => 'manage-permissions', 'display_name' => 'Quản lý Quyền hạn', 'group' => 'Hệ thống'],
            ['name' => 'access-backend', 'display_name' => 'Truy cập khu vực quản trị', 'group' => 'Hệ thống'],
            ['name' => 'view-timeline', 'display_name' => 'Xem Timeline hoạt động', 'group' => 'Tính năng'],
            ['name' => 'manage-features', 'display_name' => 'Quản lý Stock / News / Portfolio / Sync', 'group' => 'Tính năng'],
            ['name' => 'manage-queue', 'display_name' => 'Giám sát Queue', 'group' => 'Hệ thống'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission['name']], $permission);
        }

        $roleToPermissions = [
            Role::ADMIN        => ['manage-users', 'manage-roles', 'manage-permissions', 'access-backend', 'view-timeline', 'manage-features', 'manage-queue'],
            Role::WEBADMIN     => ['access-backend', 'view-timeline'],
            Role::ADMIN_SUPPORT => ['access-backend', 'view-timeline', 'manage-features'],
            Role::USER         => [],
        ];

        foreach ($roleToPermissions as $roleName => $permissionNames) {
            $role = Role::where('name', $roleName)->first();

            if (!$role || empty($permissionNames)) {
                continue;
            }

            $ids = Permission::whereIn('name', $permissionNames)->pluck('id');
            $role->permissions()->syncWithoutDetaching($ids);
        }

        $this->command->info('Đã tạo 7 permission và gán vào 4 role mặc định.');
    }
}
