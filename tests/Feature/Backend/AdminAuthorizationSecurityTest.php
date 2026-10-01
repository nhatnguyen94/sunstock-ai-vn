<?php

namespace Tests\Feature\Backend;

use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Portfolio;
use App\Models\PortfolioItem;
use App\Models\PortfolioTransaction;
use App\Models\Role;
use App\Models\Stock;
use App\Models\User;
use App\Models\WatchlistItem;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Authorization attack simulations: horizontal (user B against user A's data), vertical (who may change users, roles
 * and permissions, privilege escalation through delegated permissions), lock-out of the administration area, audit
 * trail, information leaks. Every test describes the attack in its name.
 */
class AdminAuthorizationSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Passw0rd!x';

    private User $a;      // victim, ordinary user

    private User $b;      // attacker, ordinary user

    private User $web;    // webadmin

    private User $sup;    // adminsupport

    private User $admin;

    private Portfolio $pa;

    private PortfolioItem $ia;

    private PortfolioTransaction $ta;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $mk = fn (string $email, string $role) => tap(User::factory()->create(['email' => $email, 'password' => Hash::make(self::PASSWORD)]), fn ($u) => $u->assignRole($role))->fresh();
        $this->a = $mk('a@x.test', Role::USER);
        $this->b = $mk('b@x.test', Role::USER);
        $this->web = $mk('web@x.test', Role::WEBADMIN);
        $this->sup = $mk('sup@x.test', Role::ADMIN_SUPPORT);
        $this->admin = $mk('admin@x.test', Role::ADMIN);

        Stock::create(['symbol' => 'VCB']);
        $this->pa = Portfolio::create(['user_id' => $this->a->id, 'name' => 'A-secret', 'total_invested' => 1000, 'current_value' => 1000, 'is_active' => true]);
        $this->ia = PortfolioItem::create(['portfolio_id' => $this->pa->id, 'stock_symbol' => 'VCB', 'stock_name' => 'VCB', 'quantity' => 10, 'buy_price' => 100, 'current_price' => 100, 'buy_date' => now()->toDateString()]);
        $this->ta = PortfolioTransaction::create(['portfolio_id' => $this->pa->id, 'stock_symbol' => 'VCB', 'stock_name' => 'VCB', 'type' => 'buy', 'quantity' => 10, 'price' => 100, 'fee' => 0, 'traded_at' => now()->toDateString(), 'cost_basis' => 1000]);
        WatchlistItem::create(['user_id' => $this->a->id, 'symbol' => 'VCB']);
    }

    /** Sign in as another person without carrying over the previous actor's session (AuthenticateSession would end it). */
    private function as(User $user): static
    {
        $this->app['auth']->forgetGuards();
        $this->app['session.store']->flush();
        $this->flushSession();

        return $this->actingAs($user);
    }

    private function role(string $name): Role
    {
        return Role::where('name', $name)->firstOrFail();
    }

    private function perm(string $name): Permission
    {
        return Permission::where('name', $name)->firstOrFail();
    }

    private function delegate(string $role, string ...$permissions): void
    {
        $this->role($role)->permissions()->syncWithoutDetaching(array_map(fn ($p) => $this->perm($p)->id, $permissions));
        $this->web = $this->web->fresh();
    }

    // ═════════════ horizontal: user B against user A's resources ═════════════

    #[Group('authzSecurity')]
    public function test_reading_someone_elses_portfolio_in_any_form_is_a_404(): void
    {
        $id = $this->pa->id;
        foreach (["/portfolio/$id", "/portfolio/$id/edit", "/portfolio/$id/export", "/portfolio/$id/transactions/export", "/portfolio/$id/add-stock", "/portfolio/$id/rebalance-suggestions"] as $url) {
            $this->assertContains($this->as($this->b)->get($url)->status(), [403, 404], "GET $url");
        }
    }

    #[Group('authzSecurity')]
    public function test_writing_to_someone_elses_portfolio_changes_nothing(): void
    {
        $id = $this->pa->id;
        $this->as($this->b);

        $this->put("/portfolio/$id", ['name' => 'pwned', 'description' => 'x', 'is_active' => 1]);
        $this->assertSame('A-secret', $this->pa->fresh()->name);

        $this->post("/portfolio/$id/transactions", ['type' => 'buy', 'stock_symbol' => 'VCB', 'quantity' => 5, 'price' => 100, 'fee' => 0, 'traded_at' => now()->toDateString()]);
        $this->assertSame(1, PortfolioTransaction::where('portfolio_id', $id)->count());

        $this->post("/portfolio/$id/add-stock", ['stock_symbol' => 'VCB', 'quantity' => 1, 'buy_price' => 1, 'buy_date' => now()->toDateString()]);
        $this->assertSame(1, PortfolioItem::where('portfolio_id', $id)->count());

        $this->put("/portfolio/item/{$this->ia->id}", ['quantity' => 999, 'buy_price' => 1, 'buy_date' => now()->toDateString()]);
        $this->assertSame(10, (int) $this->ia->fresh()->quantity);

        $this->delete("/portfolio/item/{$this->ia->id}");
        $this->assertNotNull(PortfolioItem::find($this->ia->id));

        $this->delete("/portfolio/transactions/{$this->ta->id}");
        $this->assertNotNull(PortfolioTransaction::find($this->ta->id));

        $this->post("/portfolio/$id/update-prices");
        $this->delete("/portfolio/$id");
        $this->assertNotNull(Portfolio::find($id));
    }

    #[Group('authzSecurity')]
    public function test_the_watchlist_is_scoped_to_the_caller(): void
    {
        $this->as($this->b)->delete('/watchlist/VCB');

        $this->assertSame(1, WatchlistItem::where('user_id', $this->a->id)->count());
    }

    /** @return array<string, array{string}> */
    public static function oversizedOrHostileIds(): array
    {
        return array_map(fn ($v) => [$v], [
            'negative' => '-1', 'zero' => '0', 'overflow' => '99999999999999999999', 'text' => 'abc',
            'sql' => '1%20OR%201=1', 'long digits' => str_repeat('9', 40),
        ]);
    }

    #[Group('authzSecurity')]
    #[DataProvider('oversizedOrHostileIds')]
    public function test_hostile_or_oversized_ids_are_a_404_never_a_500(string $id): void
    {
        $this->as($this->b);
        foreach (["/portfolio/$id", "/portfolio/$id/edit", "/portfolio/$id/export"] as $url) {
            $this->get($url)->assertNotFound();
        }
        // these two answer with a redirect and a flash error when the id is simply unknown: fine, as long as it is never a 500
        $this->assertContains($this->delete("/portfolio/item/$id")->status(), [302, 404]);
        $this->assertContains($this->delete("/portfolio/transactions/$id")->status(), [302, 404]);

        $this->as($this->admin);
        foreach (["/admin/users/$id", "/admin/users/$id/edit", "/admin/portfolios/$id", "/admin/stocks/$id"] as $url) {
            $this->get($url)->assertNotFound();
        }
        $this->delete("/admin/users/$id")->assertNotFound();
    }

    // ═════════════ vertical: who can reach what ═════════════

    #[Group('authzSecurity')]
    public function test_a_guest_and_an_ordinary_user_cannot_reach_any_admin_endpoint(): void
    {
        foreach (['/admin', '/admin/users', '/admin/roles', '/admin/permissions', '/admin/queue', '/admin/queue/stats', '/admin/timeline', '/admin/sync-status', '/admin/stocks', '/admin/portfolios', '/admin/account'] as $u) {
            $this->app['auth']->forgetGuards();
            $this->flushSession();
            $this->get($u)->assertRedirect(route('admin.login'));
            $this->as($this->b)->get($u)->assertRedirect(route('home'));
        }
        $this->as($this->b)->post('/admin/users', ['name' => 'x'])->assertRedirect(route('home'));
        $this->as($this->b)->delete("/admin/users/{$this->a->id}")->assertRedirect(route('home'));
    }

    #[Group('authzSecurity')]
    public function test_webadmin_and_adminsupport_get_403_for_what_they_were_not_given(): void
    {
        $this->as($this->web)->get('/admin/users')->assertForbidden();
        $this->as($this->web)->get('/admin/roles')->assertForbidden();
        $this->as($this->web)->get('/admin/stocks')->assertForbidden();
        $this->as($this->sup)->get('/admin/users')->assertForbidden();
        $this->as($this->sup)->get('/admin/queue')->assertForbidden();
        $this->as($this->sup)->delete("/admin/users/{$this->a->id}")->assertForbidden();
        $this->as($this->sup)->put("/admin/roles/{$this->role(Role::USER)->id}", ['name' => 'user', 'display_name' => 'x', 'permissions' => [$this->perm('manage-users')->id]])->assertForbidden();
    }

    /** @return array<string, array{string, string, int}> method, uri with {user}/{role}/{permission}, expected status */
    public static function delegatedHolderRoutes(): array
    {
        return [
            'users list is readable' => ['get', '/admin/users', 200],
            'user detail is readable' => ['get', '/admin/users/{user}', 200],
            'user create form' => ['get', '/admin/users/create', 403],
            'user store' => ['post', '/admin/users', 403],
            'user edit form' => ['get', '/admin/users/{user}/edit', 403],
            'user update' => ['put', '/admin/users/{user}', 403],
            'user delete' => ['delete', '/admin/users/{user}', 403],
            'user verify' => ['post', '/admin/users/{user}/verify', 403],
            'user unverify' => ['post', '/admin/users/{user}/unverify', 403],
            'roles list is readable' => ['get', '/admin/roles', 200],
            'role create form' => ['get', '/admin/roles/create', 403],
            'role store' => ['post', '/admin/roles', 403],
            'role edit form' => ['get', '/admin/roles/{role}/edit', 403],
            'role update' => ['put', '/admin/roles/{role}', 403],
            'role delete' => ['delete', '/admin/roles/{role}', 403],
            'permissions list is readable' => ['get', '/admin/permissions', 200],
            'permission create form' => ['get', '/admin/permissions/create', 403],
            'permission store' => ['post', '/admin/permissions', 403],
            'permission edit form' => ['get', '/admin/permissions/{permission}/edit', 403],
            'permission update' => ['put', '/admin/permissions/{permission}', 403],
            'permission delete' => ['delete', '/admin/permissions/{permission}', 403],
        ];
    }

    #[Group('authzSecurity')]
    #[DataProvider('delegatedHolderRoutes')]
    public function test_even_with_the_permission_delegated_only_the_admin_role_may_change_users_roles_and_permissions(string $method, string $uri, int $expected): void
    {
        $this->delegate(Role::WEBADMIN, 'manage-users', 'manage-roles', 'manage-permissions');
        $uri = str_replace(['{user}', '{role}', '{permission}'], [$this->a->id, $this->role(Role::USER)->id, $this->perm('view-timeline')->id], $uri);

        $r = $this->as($this->web)->{$method}($uri, ['name' => 'x', 'email' => 'x@x.test', 'display_name' => 'x', 'roles' => [Role::ADMIN], 'status' => 1]);

        $this->assertSame($expected, $r->status(), "$method $uri");
    }

    #[Group('authzSecurity')]
    public function test_the_admin_role_itself_still_passes_every_one_of_those_gates(): void
    {
        $this->as($this->admin)->get('/admin/users/create')->assertOk();
        $this->as($this->admin)->get("/admin/users/{$this->a->id}/edit")->assertOk();
        $this->as($this->admin)->get('/admin/roles/create')->assertOk();
        $this->as($this->admin)->get('/admin/permissions/create')->assertOk();
    }

    #[Group('authzSecurity')]
    public function test_a_delegated_manage_users_holder_cannot_promote_themselves_or_create_an_admin(): void
    {
        $this->delegate(Role::WEBADMIN, 'manage-users');

        $this->as($this->web)->put("/admin/users/{$this->web->id}", ['name' => 'web', 'email' => 'web@x.test', 'roles' => [Role::WEBADMIN, Role::ADMIN], 'status' => 1])->assertForbidden();
        $this->assertFalse($this->web->fresh()->hasRole(Role::ADMIN));

        $this->as($this->web)->post('/admin/users', ['name' => 'backdoor', 'email' => 'backdoor@x.test', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD, 'roles' => [Role::ADMIN], 'status' => 1])->assertForbidden();
        $this->assertNull(User::where('email', 'backdoor@x.test')->first());
    }

    #[Group('authzSecurity')]
    public function test_a_delegated_manage_roles_holder_cannot_grant_permissions_to_their_own_role(): void
    {
        $this->delegate(Role::WEBADMIN, 'manage-roles');
        $all = Permission::pluck('id')->all();

        $this->as($this->web)->put("/admin/roles/{$this->role(Role::WEBADMIN)->id}", ['name' => 'webadmin', 'display_name' => 'Web', 'permissions' => $all])->assertForbidden();

        $this->assertFalse($this->web->fresh()->hasPermission('manage-users'));
        $this->assertFalse($this->web->fresh()->hasPermission('manage-permissions'));
    }

    #[Group('authzSecurity')]
    public function test_a_delegated_manage_permissions_holder_cannot_rename_or_delete_permissions(): void
    {
        $this->delegate(Role::WEBADMIN, 'manage-permissions');
        $p = $this->perm('manage-users');

        $this->as($this->web)->put("/admin/permissions/{$p->id}", ['name' => 'view-timeline-2', 'display_name' => 'x'])->assertForbidden();
        $this->as($this->web)->delete("/admin/permissions/{$p->id}")->assertForbidden();

        $this->assertSame('manage-users', $p->fresh()->name);
    }

    // ═════════════ lock-out protections inside the admin role ═════════════

    #[Group('authzSecurity')]
    public function test_an_admin_cannot_strip_their_own_admin_role_block_or_delete_themselves(): void
    {
        $this->as($this->admin)->put("/admin/users/{$this->admin->id}", ['name' => 'admin', 'email' => 'admin@x.test', 'roles' => [Role::USER], 'status' => 1])
            ->assertSessionHas('error');
        $this->assertTrue($this->admin->fresh()->hasRole(Role::ADMIN));

        foreach ([User::STATUS_BLOCKED, User::STATUS_INACTIVE, User::STATUS_PENDING] as $status) {
            $this->as($this->admin)->put("/admin/users/{$this->admin->id}", ['name' => 'admin', 'email' => 'admin@x.test', 'roles' => [Role::ADMIN], 'status' => $status])->assertSessionHas('error');
            $this->assertSame(User::STATUS_ACTIVE, $this->admin->fresh()->status);
        }

        $this->as($this->admin)->delete("/admin/users/{$this->admin->id}")->assertSessionHas('error');
        $this->assertNotNull(User::find($this->admin->id));
    }

    #[Group('authzSecurity')]
    public function test_an_admin_cannot_unverify_themselves(): void
    {
        $this->as($this->admin)->post("/admin/users/{$this->admin->id}/unverify")->assertSessionHas('error');

        $this->assertNotNull($this->admin->fresh()->email_verified_at);
        $this->assertSame(User::STATUS_ACTIVE, $this->admin->fresh()->status);
    }

    #[Group('authzSecurity')]
    public function test_one_admin_can_still_manage_another_admin(): void
    {
        $second = tap(User::factory()->create(['email' => 'second@x.test']), fn ($u) => $u->assignRole(Role::ADMIN))->fresh();

        $this->as($this->admin)->put("/admin/users/{$second->id}", ['name' => 'second', 'email' => 'second@x.test', 'roles' => [Role::USER], 'status' => 1])->assertSessionHasNoErrors();

        $this->assertFalse($second->fresh()->hasRole(Role::ADMIN));
    }

    #[Group('authzSecurity')]
    public function test_a_system_role_cannot_be_renamed_which_would_lock_every_admin_out(): void
    {
        foreach ([Role::ADMIN, Role::WEBADMIN, Role::ADMIN_SUPPORT, Role::USER] as $name) {
            $role = $this->role($name);
            $this->as($this->admin)->put("/admin/roles/{$role->id}", ['name' => 'renamed-'.$name, 'display_name' => $role->display_name, 'permissions' => $role->permissions->pluck('id')->all()])
                ->assertSessionHas('error');

            $this->assertSame($name, $role->fresh()->name);
        }
        $this->assertTrue($this->admin->fresh()->canAccessBackend());
    }

    #[Group('authzSecurity')]
    public function test_system_roles_can_still_have_their_display_name_and_permissions_edited_and_custom_roles_renamed(): void
    {
        $webadmin = $this->role(Role::WEBADMIN);
        $this->as($this->admin)->put("/admin/roles/{$webadmin->id}", ['name' => 'webadmin', 'display_name' => 'Quản trị web', 'permissions' => [$this->perm('view-timeline')->id, $this->perm('manage-features')->id]])
            ->assertSessionHasNoErrors();
        $this->assertSame('Quản trị web', $webadmin->fresh()->display_name);
        $this->assertTrue($webadmin->fresh()->hasPermission('manage-features'));

        $custom = Role::create(['name' => 'moderator', 'display_name' => 'Mod']);
        $this->as($this->admin)->put("/admin/roles/{$custom->id}", ['name' => 'moderator-2', 'display_name' => 'Mod', 'permissions' => []])->assertSessionHasNoErrors();
        $this->assertSame('moderator-2', $custom->fresh()->name);
    }

    #[Group('authzSecurity')]
    public function test_core_permissions_cannot_be_renamed_and_the_admin_role_cannot_lose_them(): void
    {
        foreach (['manage-roles', 'manage-permissions'] as $name) {
            $core = $this->perm($name);
            $this->as($this->admin)->put("/admin/permissions/{$core->id}", ['name' => 'something-else', 'display_name' => 'x'])->assertSessionHas('error');
            $this->assertSame($name, $core->fresh()->name);
        }

        $adminRole = $this->role(Role::ADMIN);
        $this->as($this->admin)->put("/admin/roles/{$adminRole->id}", ['name' => 'admin', 'display_name' => 'Admin', 'permissions' => []])->assertSessionHas('error');
        $this->assertTrue($adminRole->fresh()->hasPermission('manage-roles') && $adminRole->fresh()->hasPermission('manage-permissions'));
    }

    #[Group('authzSecurity')]
    public function test_a_non_core_permission_can_be_renamed_and_the_admin_role_may_gain_more(): void
    {
        $p = Permission::create(['name' => 'manage-alerts', 'display_name' => 'Alerts']);
        $this->as($this->admin)->put("/admin/permissions/{$p->id}", ['name' => 'manage-alerts-v2', 'display_name' => 'Alerts'])->assertSessionHasNoErrors();
        $this->assertSame('manage-alerts-v2', $p->fresh()->name);

        $adminRole = $this->role(Role::ADMIN);
        $ids = Permission::pluck('id')->all();
        $this->as($this->admin)->put("/admin/roles/{$adminRole->id}", ['name' => 'admin', 'display_name' => 'Admin', 'permissions' => $ids])->assertSessionHasNoErrors();
        $this->assertTrue($adminRole->fresh()->hasPermission('manage-alerts-v2'));
    }

    // ═════════════ the admin forms obey the same policies as the public ones ═════════════

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function badAdminUserForms(): array
    {
        return [
            'script in the name' => [['name' => '<script>alert(1)</script>'], 'name'],
            'one letter name' => [['name' => 'A'], 'name'],
            'weak password' => [['password' => 'abcdefgh', 'password_confirmation' => 'abcdefgh'], 'password'],
            'password without digit' => [['password' => 'abcdefghij', 'password_confirmation' => 'abcdefghij'], 'password'],
            'huge password' => [['password' => str_repeat('a1', 500_000), 'password_confirmation' => str_repeat('a1', 500_000)], 'password'],
            'password mismatch' => [['password_confirmation' => 'different-9'], 'password'],
            'bad e-mail' => [['email' => 'nobody'], 'email'],
            'array e-mail' => [['email' => ['a@b.test']], 'email'],
            'unknown status' => [['status' => 3], 'status'],
            'status as text' => [['status' => 'admin'], 'status'],
            'missing status' => [['status' => ''], 'status'],
            'unknown role' => [['roles' => ['superuser']], 'roles.0'],
            'no role' => [['roles' => []], 'roles'],
            'role as text' => [['roles' => 'admin'], 'roles'],
        ];
    }

    #[Group('authzSecurity')]
    #[DataProvider('badAdminUserForms')]
    public function test_the_admin_create_user_form_rejects_hostile_or_weak_input(array $override, string $field): void
    {
        $before = User::count();
        $form = $override + ['name' => 'Nguyễn Văn B', 'email' => 'new@x.test', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD, 'roles' => [Role::USER], 'status' => 1];

        $this->as($this->admin)->post('/admin/users', $form)->assertSessionHasErrors($field);

        $this->assertSame($before, User::count());
    }

    #[Group('authzSecurity')]
    public function test_the_admin_update_form_applies_the_same_rules_and_keeps_the_password_when_left_empty(): void
    {
        $hash = $this->a->password;

        $this->as($this->admin)->put("/admin/users/{$this->a->id}", ['name' => '<b>x</b>', 'email' => 'a@x.test', 'roles' => [Role::USER], 'status' => 1])->assertSessionHasErrors('name');
        $this->as($this->admin)->put("/admin/users/{$this->a->id}", ['name' => 'abcdefg', 'email' => 'a@x.test', 'password' => 'short1', 'password_confirmation' => 'short1', 'roles' => [Role::USER], 'status' => 1])->assertSessionHasErrors('password');
        $this->as($this->admin)->put("/admin/users/{$this->a->id}", ['name' => 'Tên mới', 'email' => 'A@X.TEST', 'roles' => [Role::USER], 'status' => 1])->assertSessionHasNoErrors();

        $fresh = $this->a->fresh();
        $this->assertSame('Tên mới', $fresh->name);
        $this->assertSame('a@x.test', $fresh->email);
        $this->assertSame($hash, $fresh->password);
    }

    #[Group('authzSecurity')]
    public function test_an_email_already_used_by_someone_else_cannot_be_taken_in_another_case(): void
    {
        $this->as($this->admin)->put("/admin/users/{$this->a->id}", ['name' => 'Tên A', 'email' => 'B@X.TEST', 'roles' => [Role::USER], 'status' => 1])->assertSessionHasErrors('email');
    }

    #[Group('authzSecurity')]
    public function test_the_admin_stock_form_only_accepts_ticker_shaped_symbols(): void
    {
        $this->as($this->admin);
        foreach (['vcb2', 'A', 'WAYTOOLONGSYMBOL', 'FPT; DROP', '../etc', "FP\nT", 'F P T', 'FPT<script>'] as $bad) {
            $this->post('/admin/stocks', ['symbol' => $bad, 'name' => 'x'])->assertSessionHasErrors('symbol');
        }
        $this->post('/admin/stocks', ['symbol' => 'FPT', 'name' => 'FPT Corp'])->assertSessionHasNoErrors();
        $this->assertNotNull(Stock::where('symbol', 'FPT')->first());
    }

    // ═════════════ information leaks ═════════════

    #[Group('authzSecurity')]
    public function test_the_dashboard_only_shows_user_portfolio_and_activity_data_to_roles_allowed_to_see_it(): void
    {
        ActivityLog::create(['user_id' => $this->a->id, 'user_name' => 'Người Bí Mật', 'event_type' => 'admin_action', 'description' => 'Việc riêng tư', 'created_at' => now()]);

        // webadmin holds only view-timeline: sees the activity feed, but no users and no portfolios
        $html = $this->as($this->web)->get('/admin')->assertOk()->getContent();
        $this->assertStringContainsString('Việc riêng tư', $html);
        $this->assertStringNotContainsString('a@x.test', $html);
        $this->assertStringNotContainsString('A-secret', $html);
        $this->assertStringNotContainsString('Users mới nhất', $html);

        // take view-timeline away and the feed disappears too
        $this->role(Role::WEBADMIN)->permissions()->detach($this->perm('view-timeline')->id);
        $html = $this->as($this->web->fresh())->get('/admin')->assertOk()->getContent();
        $this->assertStringNotContainsString('Việc riêng tư', $html);
        $this->assertStringNotContainsString('Hoạt động gần đây', $html);

        $html = $this->as($this->sup)->get('/admin')->assertOk()->getContent();    // adminsupport: manage-features, not users
        $this->assertStringContainsString('A-secret', $html);
        $this->assertStringNotContainsString('a@x.test', $html);

        $html = $this->as($this->admin)->get('/admin')->assertOk()->getContent();
        $this->assertStringContainsString('a@x.test', $html);
        $this->assertStringContainsString('A-secret', $html);
    }

    #[Group('authzSecurity')]
    public function test_a_failing_sync_does_not_hand_the_exception_text_to_the_browser(): void
    {
        Artisan::shouldReceive('call')->andThrow(new \RuntimeException('SECRET /var/www/html/.env DB_PASSWORD=hunter2'));

        $r = $this->as($this->sup)->postJson('/admin/sync-status/trigger/sync:news');

        $r->assertStatus(500);
        $this->assertStringNotContainsString('SECRET', $r->getContent());
        $this->assertStringNotContainsString('hunter2', $r->getContent());
    }

    #[Group('authzSecurity')]
    public function test_the_sync_trigger_only_runs_whitelisted_commands(): void
    {
        Artisan::shouldReceive('call')->never();

        foreach (['migrate:fresh', 'db:wipe', '../x', 'sync:news;rm', 'queue:flush'] as $key) {
            $this->assertContains($this->as($this->sup)->postJson('/admin/sync-status/trigger/'.rawurlencode($key))->status(), [404, 422], $key);
        }
    }

    // ═════════════ sessions, CSRF ═════════════

    #[Group('authzSecurity')]
    public function test_resetting_a_users_password_from_the_admin_signs_their_open_sessions_out(): void
    {
        $oldStamp = hash_hmac('sha256', $this->b->getAuthPassword(), config('app.key'));

        $this->as($this->admin)->put("/admin/users/{$this->b->id}", ['name' => 'Người B', 'email' => 'b@x.test', 'password' => 'N3w-passw0rd!', 'password_confirmation' => 'N3w-passw0rd!', 'roles' => [Role::USER], 'status' => 1]);

        $this->as($this->b->fresh())->withSession(['password_hash_web' => $oldStamp])->get('/profile')->assertRedirect('/login');
    }

    #[Group('authzSecurity')]
    public function test_every_state_changing_admin_request_needs_the_csrf_token_and_the_right_method(): void
    {
        $this->app['env'] = 'local';
        $this->actingAs($this->admin);

        $this->post("/admin/users/{$this->b->id}/verify")->assertStatus(419);
        $this->post("/admin/users/{$this->b->id}/unverify")->assertStatus(419);
        $this->delete('/admin/queue/failed')->assertStatus(419);
        $this->delete("/admin/users/{$this->b->id}")->assertStatus(419);
        $this->put("/admin/roles/{$this->role(Role::USER)->id}", [])->assertStatus(419);
        $this->post('/admin/permissions', [])->assertStatus(419);
        $this->patch("/admin/portfolios/{$this->pa->id}/toggle-status")->assertStatus(419);
        $this->get("/admin/users/{$this->b->id}/verify")->assertStatus(405);
    }

    // ═════════════ audit trail ═════════════

    private function lastLog(): ActivityLog
    {
        return ActivityLog::latest('id')->firstOrFail();
    }

    #[Group('authzSecurity')]
    public function test_every_sensitive_admin_action_leaves_an_audit_record_naming_the_actor_and_target_without_secrets(): void
    {
        $this->as($this->admin);
        $log = fn (string $contains) => ActivityLog::where('description', 'like', "%{$contains}%")->latest('id')->first();

        $this->post('/admin/users', ['name' => 'Audit Me', 'email' => 'audit@x.test', 'password' => self::PASSWORD.'z', 'password_confirmation' => self::PASSWORD.'z', 'roles' => [Role::USER], 'status' => 1]);
        $created = User::where('email', 'audit@x.test')->firstOrFail();
        $this->assertNotNull($log('Tạo user audit@x.test'));

        $this->put("/admin/users/{$created->id}", ['name' => 'Audit Me', 'email' => 'audit@x.test', 'password' => 'N3w-passw0rd!', 'password_confirmation' => 'N3w-passw0rd!', 'roles' => [Role::WEBADMIN], 'status' => 4]);
        $update = $log('Cập nhật user audit@x.test');
        $this->assertNotNull($update);
        $this->assertSame($this->admin->id, $update->user_id);
        $this->assertSame([Role::USER], $update->properties['before']['roles']);
        $this->assertSame([Role::WEBADMIN], $update->properties['after']['roles']);
        $this->assertSame(4, $update->properties['after']['status']);
        $this->assertTrue($update->properties['password_changed']);

        $this->post("/admin/users/{$created->id}/verify");
        $this->post("/admin/users/{$created->id}/unverify");
        $this->assertNotNull($log('Hủy xác thực email audit@x.test'));

        $this->post('/admin/permissions', ['name' => 'audit-perm', 'display_name' => 'x']);
        $this->assertNotNull($log('Tạo quyền audit-perm'));
        $this->post('/admin/roles', ['name' => 'audit-role', 'display_name' => 'x', 'permissions' => [$this->perm('view-timeline')->id]]);
        $this->assertNotNull($log('Tạo vai trò audit-role'));

        $role = Role::where('name', 'audit-role')->firstOrFail();
        $this->put("/admin/roles/{$role->id}", ['name' => 'audit-role', 'display_name' => 'y', 'permissions' => []]);
        $this->assertNotNull($log('Cập nhật vai trò audit-role'));
        $this->delete("/admin/roles/{$role->id}");
        $this->assertNotNull($log('Xoá vai trò audit-role'));

        $perm = Permission::where('name', 'audit-perm')->firstOrFail();
        $this->delete("/admin/permissions/{$perm->id}");
        $this->assertNotNull($log('Xoá quyền audit-perm'));

        $this->patch("/admin/portfolios/{$this->pa->id}/toggle-status");
        $this->assertNotNull($log("Portfolio #{$this->pa->id} đã được"));
        $this->delete("/admin/portfolios/{$this->pa->id}");
        $this->assertNotNull($log("Xóa portfolio #{$this->pa->id}"));

        $this->delete("/admin/users/{$created->id}");
        $this->assertNotNull($log('Xóa user audit@x.test'));

        $everything = ActivityLog::all()->map(fn ($l) => $l->description.json_encode($l->properties))->implode(' ');
        $this->assertStringNotContainsString(self::PASSWORD, $everything);
        $this->assertStringNotContainsString('N3w-passw0rd', $everything);
        $this->assertStringNotContainsString('$2y$', $everything);

        foreach (ActivityLog::all() as $row) {
            $this->assertSame($this->admin->id, $row->user_id);
            $this->assertNotEmpty($row->ip_address);
        }
    }

    #[Group('authzSecurity')]
    public function test_failed_and_refused_admin_actions_are_not_recorded_as_done(): void
    {
        $before = ActivityLog::count();

        $this->as($this->admin)->put("/admin/users/{$this->admin->id}", ['name' => 'admin', 'email' => 'admin@x.test', 'roles' => [Role::USER], 'status' => 1]);
        $this->as($this->admin)->post('/admin/users', ['name' => '<x>', 'email' => 'q@x.test', 'password' => 'a', 'password_confirmation' => 'a', 'roles' => [Role::USER], 'status' => 1]);
        $this->as($this->web)->delete("/admin/users/{$this->a->id}");

        $this->assertSame($before, ActivityLog::count());
    }
}
