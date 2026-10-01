<?php

namespace Tests\Feature\Database;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DemoStaffSeeder;
use Database\Seeders\DemoUsersSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

class DemoStaffSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function accounts(): array
    {
        return User::where('email', 'like', '%@sunstock.test')->get()->keyBy(fn ($u) => explode('@', $u->email)[0])->all();
    }

    #[Group('demoUsers')]
    public function test_it_creates_staff_and_status_accounts_with_the_shared_password(): void
    {
        $this->seed(DemoStaffSeeder::class);
        $users = $this->accounts();

        $this->assertSame(['blocked', 'inactive', 'pending', 'support', 'webadmin'], collect(array_keys($users))->sort()->values()->all());
        foreach ($users as $u) {
            $this->assertTrue(Hash::check('abc123456789', $u->password));
            $this->assertNotNull($u->profile);
        }
        $this->assertSame('abc123456789', DemoStaffSeeder::PASSWORD);
        $this->assertSame(DemoUsersSeeder::PASSWORD, DemoStaffSeeder::PASSWORD);

        $this->assertSame([Role::WEBADMIN], $users['webadmin']->getRoleNames());
        $this->assertSame([Role::ADMIN_SUPPORT], $users['support']->getRoleNames());
        foreach (['pending', 'inactive', 'blocked'] as $name) {
            $this->assertSame([Role::USER], $users[$name]->getRoleNames());
        }
        $this->assertSame(User::STATUS_ACTIVE, $users['webadmin']->status);
        $this->assertSame(User::STATUS_ACTIVE, $users['support']->status);
        $this->assertSame(User::STATUS_PENDING, $users['pending']->status);
        $this->assertSame(User::STATUS_INACTIVE, $users['inactive']->status);
        $this->assertSame(User::STATUS_BLOCKED, $users['blocked']->status);
    }

    #[Group('demoUsers')]
    public function test_none_of_the_staff_accounts_is_an_admin(): void
    {
        $this->seed(DemoStaffSeeder::class);

        foreach ($this->accounts() as $u) {
            $this->assertFalse($u->hasRole(Role::ADMIN), $u->email);
        }
    }

    #[Group('demoUsers')]
    public function test_it_is_idempotent_and_does_not_undo_a_status_changed_while_testing(): void
    {
        $this->seed(DemoStaffSeeder::class);
        User::where('email', 'blocked@sunstock.test')->first()->forceFill(['status' => User::STATUS_ACTIVE])->save();
        $hash = User::where('email', 'webadmin@sunstock.test')->value('password');

        $this->seed(DemoStaffSeeder::class);

        $this->assertCount(5, $this->accounts());
        $this->assertSame(User::STATUS_ACTIVE, User::where('email', 'blocked@sunstock.test')->value('status'));
        $this->assertSame($hash, User::where('email', 'webadmin@sunstock.test')->value('password'));
    }

    #[Group('demoUsers')]
    public function test_it_refuses_to_run_outside_local_and_testing(): void
    {
        $this->app['env'] = 'production';

        (new DemoStaffSeeder)->run();

        $this->assertCount(0, $this->accounts());
    }

    #[Group('demoUsers')]
    public function test_the_accounts_behave_as_advertised_at_the_login_forms(): void
    {
        $this->seed(DemoStaffSeeder::class);

        // staff can open the admin area...
        $this->post('/admin/login', ['email' => 'webadmin@sunstock.test', 'password' => 'abc123456789'])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated();
        // ...but cannot change users or roles
        $this->get('/admin/users')->assertForbidden();
        $this->post('/admin/users', ['name' => 'x'])->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->post('/admin/login', ['email' => 'support@sunstock.test', 'password' => 'abc123456789'])->assertRedirect(route('admin.dashboard'));
        $this->get('/admin/stocks')->assertOk();
        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/queue')->assertForbidden();

        // pending / inactive / blocked cannot sign in anywhere
        foreach (['pending', 'inactive', 'blocked'] as $name) {
            $this->app['auth']->forgetGuards();
            $this->flushSession();
            $this->post('/login', ['email' => "$name@sunstock.test", 'password' => 'abc123456789'])->assertSessionHasErrors('email');
            $this->assertGuest();
        }
    }

    #[Group('demoUsers')]
    public function test_the_other_seeders_create_active_accounts(): void
    {
        $this->seed(DemoUsersSeeder::class);
        $this->seed(AdminUserSeeder::class);

        foreach (User::all() as $u) {
            $this->assertSame(User::STATUS_ACTIVE, $u->status, $u->email);
            $this->assertNotNull($u->email_verified_at);
        }
        $this->post('/login', ['email' => 'demo1@sunstock.test', 'password' => 'abc123456789'])->assertRedirect();
        $this->assertAuthenticated();
    }
}
