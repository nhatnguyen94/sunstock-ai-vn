<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Khởi tạo 4 vai trò chính xác theo yêu cầu (chỉ tạo nếu chưa tồn tại)
        $roles = [
            [
                'name' => Role::ADMIN,
                'display_name' => 'Quản trị viên',
            ],
            [
                'name' => Role::WEBADMIN,
                'display_name' => 'Quản trị web',
            ],
            [
                'name' => Role::ADMIN_SUPPORT,
                'display_name' => 'Hỗ trợ quản trị',
            ],
            [
                'name' => Role::USER,
                'display_name' => 'Người dùng',
            ],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['name' => $role['name']], 
                ['display_name' => $role['display_name']]
            );
        }

        $this->command->info('Đã tạo thành công 4 vai trò: Admin, Webadmin, AdminSupport, User');
    }
}
