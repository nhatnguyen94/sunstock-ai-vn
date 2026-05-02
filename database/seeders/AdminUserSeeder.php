<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Tạo user admin để test
        $admin = User::firstOrCreate(
            ['email' => 'sunadmin@example.com'],
            [
                'name' => 'sunadmin',
                'email' => 'sunadmin@example.com',
                'password' => Hash::make('12345678'),
                'email_verified_at' => now(),
            ]
        );

        // Tạo profile cho admin
        UserProfile::firstOrCreate(
            ['user_id' => $admin->id],
            [
                'user_id' => $admin->id,
                'username' => 'sunadmin',
                'mobile' => null,
            ]
        );

        // Gán role admin
        $adminRole = Role::where('name', Role::ADMIN)->first();
        if ($adminRole && !$admin->hasRole(Role::ADMIN)) {
            $admin->roles()->syncWithoutDetaching([$adminRole->id]);
        }

        $this->command->info('Đã tạo user admin: sunadmin@example.com / 12345678');
        $this->command->info('User có quyền Admin để test backend!');
    }
}
