<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Accounts for manually testing the administration area and the account statuses, all with the shared demo password:
 *
 *   webadmin  - role webadmin      (can sign in to /admin, but is NOT an admin: view-only, no manage-* permissions)
 *   support   - role adminsupport  (can sign in to /admin, manages stocks/news/portfolios, NOT users/roles/queue)
 *   pending   - role user, status 2 pending   (e-mail not confirmed: cannot sign in)
 *   inactive  - role user, status 0 inactive  (switched off by an admin: cannot sign in)
 *   blocked   - role user, status 4 blocked   (cannot sign in)
 *
 *   php artisan db:seed --class=DemoStaffSeeder
 *
 * LOCAL ONLY, like DemoUsersSeeder: the password is public, so it refuses to run outside local/testing. Idempotent:
 * accounts are found by e-mail and an existing one is left exactly as it is (so statuses you changed while testing
 * are not reset).
 */
class DemoStaffSeeder extends Seeder
{
    public const PASSWORD = DemoUsersSeeder::PASSWORD;

    private const DOMAIN = 'sunstock.test';

    /** username => [display name, role, status] */
    private const ACCOUNTS = [
        'webadmin' => ['Demo Web Admin', Role::WEBADMIN, User::STATUS_ACTIVE],
        'support' => ['Demo Admin Support', Role::ADMIN_SUPPORT, User::STATUS_ACTIVE],
        'pending' => ['Demo Chờ xác thực', Role::USER, User::STATUS_PENDING],
        'inactive' => ['Demo Ngưng hoạt động', Role::USER, User::STATUS_INACTIVE],
        'blocked' => ['Demo Bị chặn', Role::USER, User::STATUS_BLOCKED],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->error('DemoStaffSeeder chỉ chạy ở môi trường local/testing (mật khẩu chung là công khai).');

            return;
        }

        foreach (self::ACCOUNTS as $username => [$name, $role, $status]) {
            $email = "{$username}@".self::DOMAIN;

            $user = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => Hash::make(self::PASSWORD)]);

            if ($user->wasRecentlyCreated) {
                $user->applyStatus($status);   // status is not mass-assignable; also sets/clears the e-mail confirmation
                if (in_array($status, [User::STATUS_INACTIVE, User::STATUS_BLOCKED], true)) {
                    $user->email_verified_at = now();   // so that only the status, not the e-mail, is what keeps them out
                }
                $user->save();
            }

            UserProfile::firstOrCreate(['user_id' => $user->id], ['username' => $username, 'mobile' => null]);

            if (! $user->hasRole($role)) {
                $user->assignRole($role);
            }

            $this->command?->info(sprintf('%-9s %-28s %-13s %s', $username, $email, $role, User::statusLabels()[$status]));
        }

        $this->command?->info('Mật khẩu chung: '.self::PASSWORD);
    }
}
