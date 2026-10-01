<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Account status: 0 inactive, 1 active, 2 pending (waiting for e-mail verification), 4 blocked.
 * Only an active account with a confirmed e-mail can sign in or keep a session.
 */
class AccountStatusTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Correct-horse-9';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function user(string $state = 'active', array $attrs = []): User
    {
        $factory = User::factory();
        $factory = match ($state) {
            'pending' => $factory->unverified(),
            'inactive' => $factory->inactive(),
            'blocked' => $factory->blocked(),
            default => $factory,
        };

        return $factory->create($attrs + ['email' => "$state@example.test", 'password' => Hash::make(self::PASSWORD)]);
    }

    private function admin(string $email = 'boss@example.test', string $state = 'active'): User
    {
        $u = $this->user($state, ['email' => $email]);
        $u->assignRole(Role::ADMIN);

        return $u->fresh();
    }

    // ── the model ───────────────────────────────────────────────────────────

    #[Group('accountStatus')]
    public function test_the_status_numbers_are_the_agreed_ones_and_each_has_a_label(): void
    {
        $this->assertSame(0, User::STATUS_INACTIVE);
        $this->assertSame(1, User::STATUS_ACTIVE);
        $this->assertSame(2, User::STATUS_PENDING);
        $this->assertSame(4, User::STATUS_BLOCKED);
        $this->assertSame([1, 2, 0, 4], array_keys(User::statusLabels()));
        $this->assertSame('Hoạt động', (new User(['name' => 'x']))->forceFill(['status' => 1])->statusLabel());
        $this->assertSame('Chờ xác thực', (new User)->forceFill(['status' => 2])->statusLabel());
        $this->assertSame('Ngưng hoạt động', (new User)->forceFill(['status' => 0])->statusLabel());
        $this->assertSame('Bị chặn', (new User)->forceFill(['status' => 4])->statusLabel());
        $this->assertSame('Không rõ', (new User)->forceFill(['status' => 3])->statusLabel());
    }

    #[Group('accountStatus')]
    public function test_a_stored_account_with_no_status_given_is_pending_and_status_cannot_be_mass_assigned(): void
    {
        DB::table('users')->insert(['name' => 'raw', 'email' => 'raw@example.test', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame(User::STATUS_PENDING, User::where('email', 'raw@example.test')->first()->status);

        $viaCreate = User::create(['name' => 'sneaky', 'email' => 's@example.test', 'password' => 'Passw0rd!x', 'status' => User::STATUS_ACTIVE, 'email_verified_at' => now()]);
        $this->assertSame(User::STATUS_PENDING, $viaCreate->fresh()->status);   // `status` is not fillable
    }

    #[Group('accountStatus')]
    public function test_factory_states(): void
    {
        $this->assertSame(User::STATUS_ACTIVE, User::factory()->create()->status);
        $this->assertSame(User::STATUS_PENDING, User::factory()->unverified()->create()->status);
        $this->assertNull(User::factory()->unverified()->create()->email_verified_at);
        $this->assertSame(User::STATUS_BLOCKED, User::factory()->blocked()->create()->status);
        $this->assertSame(User::STATUS_INACTIVE, User::factory()->inactive()->create()->status);
    }

    /** @return array<string, array{int, bool, bool}> status, e-mail confirmed, may sign in */
    public static function signInMatrix(): array
    {
        return [
            'active + verified' => [1, true, true],
            'active but e-mail not confirmed' => [1, false, false],
            'pending + verified' => [2, true, false],
            'pending' => [2, false, false],
            'inactive + verified' => [0, true, false],
            'blocked + verified' => [4, true, false],
            'unknown status 3' => [3, true, false],
        ];
    }

    #[Group('accountStatus')]
    #[DataProvider('signInMatrix')]
    public function test_can_sign_in_needs_an_active_status_and_a_confirmed_email(int $status, bool $verified, bool $expected): void
    {
        $user = (new User)->forceFill(['status' => $status, 'email_verified_at' => $verified ? now() : null]);

        $this->assertSame($expected, $user->canSignIn());
        $this->assertSame($status === 1, $user->isActive());
    }

    #[Group('accountStatus')]
    public function test_apply_status_keeps_the_email_confirmation_consistent(): void
    {
        $user = (new User)->forceFill(['email_verified_at' => null]);
        $user->applyStatus(User::STATUS_ACTIVE);
        $this->assertNotNull($user->email_verified_at, 'activating by hand vouches for the address');

        $user->applyStatus(User::STATUS_PENDING);
        $this->assertNull($user->email_verified_at, 'pending means waiting for verification');
        $this->assertSame(2, $user->status);

        $confirmed = (new User)->forceFill(['email_verified_at' => now()->subDay()]);
        $when = $confirmed->email_verified_at;
        $confirmed->applyStatus(User::STATUS_BLOCKED);
        $this->assertEquals($when, $confirmed->email_verified_at, 'blocking keeps what is already confirmed');
        $confirmed->applyStatus(User::STATUS_INACTIVE);
        $this->assertEquals($when, $confirmed->email_verified_at);

        $already = (new User)->forceFill(['email_verified_at' => now()->subDay()]);
        $when = $already->email_verified_at;
        $already->applyStatus(User::STATUS_ACTIVE);
        $this->assertEquals($when, $already->email_verified_at, 'an existing confirmation date is not overwritten');
    }

    #[Group('accountStatus')]
    public function test_access_denied_messages_match_the_reason(): void
    {
        $this->assertStringContainsString('bị chặn', (new User)->forceFill(['status' => 4])->accessDeniedMessage());
        $this->assertStringContainsString('ngưng hoạt động', (new User)->forceFill(['status' => 0])->accessDeniedMessage());
        $this->assertStringContainsString('xác thực email', (new User)->forceFill(['status' => 2])->accessDeniedMessage());
        $this->assertStringContainsString('xác thực email', (new User)->forceFill(['status' => 1])->accessDeniedMessage());
    }

    // ── registration and verification ──────────────────────────────────────

    #[Group('accountStatus')]
    public function test_a_new_registration_is_pending_and_a_verified_link_activates_it(): void
    {
        Notification::fake();

        $this->post('/register', ['username' => 'newbie', 'email' => 'newbie@example.test', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])->assertRedirect('/login');
        $user = User::where('email', 'newbie@example.test')->firstOrFail();
        $this->assertSame(User::STATUS_PENDING, $user->status);
        $this->post('/login', ['email' => 'newbie@example.test', 'password' => self::PASSWORD])->assertSessionHasErrors('email');
        $this->assertGuest();

        $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($link)->assertRedirect(route('login'));

        $this->assertSame(User::STATUS_ACTIVE, $user->fresh()->status);
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->post('/login', ['email' => 'newbie@example.test', 'password' => self::PASSWORD])->assertRedirect();
        $this->assertAuthenticated();
    }

    #[Group('accountStatus')]
    public function test_a_verification_link_confirms_the_mailbox_but_never_unblocks_or_reactivates(): void
    {
        foreach (['blocked' => User::STATUS_BLOCKED, 'inactive' => User::STATUS_INACTIVE] as $state => $status) {
            $user = $this->user($state, ['email_verified_at' => null]);
            $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);

            $this->get($link);

            $this->assertSame($status, $user->fresh()->status, $state);
            $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertSessionHasErrors('email');
            $this->assertGuest();
        }
    }

    // ── login ───────────────────────────────────────────────────────────────

    #[Group('accountStatus')]
    public function test_only_an_active_verified_account_can_log_in(): void
    {
        $this->user('active');
        $this->post('/login', ['email' => 'active@example.test', 'password' => self::PASSWORD])->assertRedirect();
        $this->assertAuthenticated();
    }

    #[Group('accountStatus')]
    public function test_pending_inactive_and_blocked_accounts_are_refused_with_the_right_reason_only_after_the_password_is_right(): void
    {
        $expected = ['pending' => 'xác thực email', 'inactive' => 'ngưng hoạt động', 'blocked' => 'bị chặn'];

        foreach ($expected as $state => $reason) {
            $this->user($state);
            $this->app['auth']->forgetGuards();

            $right = $this->post('/login', ['email' => "$state@example.test", 'password' => self::PASSWORD, 'remember' => '1']);
            $right->assertSessionHasErrors('email');
            $this->assertStringContainsString($reason, session('errors')->first('email'), $state);
            $this->assertGuest();
            $this->assertEmpty(array_filter($right->headers->getCookies(), fn ($c) => str_starts_with($c->getName(), 'remember_web')), "$state got a remember cookie");

            $this->post('/login', ['email' => "$state@example.test", 'password' => 'Wrong-password-1']);
            $this->assertSame('Thông tin đăng nhập không chính xác.', session('errors')->first('email'), "$state leaks its status to someone without the password");
        }
    }

    #[Group('accountStatus')]
    public function test_the_admin_login_answers_every_non_active_account_like_a_wrong_password(): void
    {
        foreach (['pending', 'inactive', 'blocked'] as $state) {
            $u = $this->user($state);
            $u->assignRole(Role::ADMIN);

            $this->post('/admin/login', ['email' => "$state@example.test", 'password' => self::PASSWORD])->assertSessionHasErrors('email');
            $this->assertSame('Thông tin đăng nhập không chính xác.', session('errors')->first('email'), $state);
            $this->assertGuest();
        }

        $this->admin();
        $this->post('/admin/login', ['email' => 'boss@example.test', 'password' => self::PASSWORD])->assertRedirect(route('admin.dashboard'));
    }

    // ── sessions that are already open ─────────────────────────────────────

    #[Group('accountStatus')]
    public function test_blocking_a_signed_in_user_ends_their_session_on_the_next_request(): void
    {
        $user = $this->user('active');

        $this->actingAs($user)->get('/profile')->assertOk();

        $user->forceFill(['status' => User::STATUS_BLOCKED])->save();

        $this->actingAs($user->fresh())->get('/profile')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertStringContainsString('bị chặn', session('errors')->first('email'));
    }

    #[Group('accountStatus')]
    public function test_every_non_active_state_ends_a_live_session_with_its_own_message(): void
    {
        foreach ([User::STATUS_INACTIVE => 'ngưng hoạt động', User::STATUS_PENDING => 'xác thực email', User::STATUS_BLOCKED => 'bị chặn'] as $status => $reason) {
            $user = $this->user('active', ['email' => "live{$status}@example.test"]);
            $user->forceFill(['status' => $status])->save();

            $this->app['auth']->forgetGuards();
            $this->flushSession();
            $this->actingAs($user->fresh())->get('/profile')->assertRedirect(route('login'));
            $this->assertStringContainsString($reason, session('errors')->first('email'));
        }
    }

    #[Group('accountStatus')]
    public function test_withdrawing_the_email_confirmation_also_ends_the_session_even_if_the_status_still_says_active(): void
    {
        $user = $this->user('active');
        $user->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($user->fresh())->get('/profile')->assertRedirect();
        $this->assertGuest();
    }

    #[Group('accountStatus')]
    public function test_an_ajax_request_from_a_blocked_session_gets_a_401_json_not_a_redirect(): void
    {
        $user = $this->user('blocked');

        $this->actingAs($user)->getJson('/watchlist/data')->assertStatus(401)->assertJsonPath('message', 'Tài khoản của bạn hiện không được phép truy cập.');
        $this->assertGuest();
    }

    #[Group('accountStatus')]
    public function test_a_blocked_or_suspended_administrator_is_sent_to_the_admin_login_and_loses_all_admin_access(): void
    {
        foreach (['blocked', 'inactive', 'pending'] as $state) {
            $admin = $this->admin("adm-$state@example.test", $state);

            $this->app['auth']->forgetGuards();
            $this->flushSession();
            $this->actingAs($admin)->get('/admin')->assertRedirect(route('admin.login'));
            $this->assertGuest();

            $this->app['auth']->forgetGuards();
            $this->flushSession();
            $this->actingAs($admin)->get('/admin/users')->assertRedirect(route('admin.login'));
        }
    }

    #[Group('accountStatus')]
    public function test_an_active_user_is_not_disturbed_by_the_check(): void
    {
        $user = $this->user('active');

        foreach (range(1, 3) as $i) {
            $this->actingAs($user)->get('/profile')->assertOk();
        }
        $this->assertAuthenticated();
    }

    // ── admin: change a user's status ──────────────────────────────────────

    private function form(User $u, array $over = []): array
    {
        return $over + ['name' => 'Người Dùng', 'email' => $u->email, 'roles' => [Role::USER], 'status' => $u->status];
    }

    /** @return array<string, array{int, ?bool}> new status, expected e-mail confirmed after (null = unchanged) */
    public static function statusTransitions(): array
    {
        return ['to blocked keeps the confirmation' => [4, true], 'to inactive keeps the confirmation' => [0, true], 'to pending clears it' => [2, false], 'to active keeps it' => [1, true]];
    }

    #[Group('accountStatus')]
    #[DataProvider('statusTransitions')]
    public function test_an_admin_can_move_a_verified_user_between_statuses(int $new, bool $confirmed): void
    {
        $admin = $this->admin();
        $user = $this->user('active', ['email' => 'target@example.test']);

        $this->actingAs($admin)->put("/admin/users/{$user->id}", $this->form($user, ['status' => $new]))->assertSessionHasNoErrors();

        $fresh = $user->fresh();
        $this->assertSame($new, $fresh->status);
        $this->assertSame($confirmed, $fresh->email_verified_at !== null);
    }

    #[Group('accountStatus')]
    public function test_activating_a_pending_user_by_hand_confirms_their_email(): void
    {
        $admin = $this->admin();
        $user = $this->user('pending', ['email' => 'wait@example.test']);

        $this->actingAs($admin)->put("/admin/users/{$user->id}", $this->form($user, ['status' => User::STATUS_ACTIVE]));

        $this->assertSame(User::STATUS_ACTIVE, $user->fresh()->status);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    #[Group('accountStatus')]
    public function test_blocking_a_user_in_the_admin_ends_their_open_session(): void
    {
        $admin = $this->admin();
        $user = $this->user('active', ['email' => 'victim@example.test']);

        $this->actingAs($admin)->put("/admin/users/{$user->id}", $this->form($user, ['status' => User::STATUS_BLOCKED]));

        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->actingAs($user->fresh())->get('/profile')->assertRedirect(route('login'));
    }

    #[Group('accountStatus')]
    public function test_the_create_form_sets_status_and_confirmation_together(): void
    {
        $admin = $this->admin();
        $expect = [1 => true, 2 => false, 0 => false, 4 => false];   // inactive/blocked created by hand are not auto-confirmed

        foreach ($expect as $status => $confirmed) {
            $this->actingAs($admin)->post('/admin/users', ['name' => "Tạo $status", 'email' => "made{$status}@example.test", 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD, 'roles' => [Role::USER], 'status' => $status])->assertSessionHasNoErrors();

            $u = User::where('email', "made{$status}@example.test")->firstOrFail();
            $this->assertSame($status, $u->status);
            $this->assertSame($confirmed, $u->email_verified_at !== null, "status $status");
            $this->assertNotNull($u->profile);
        }
    }

    #[Group('accountStatus')]
    public function test_admin_verify_activates_a_pending_account_but_leaves_a_blocked_one_blocked(): void
    {
        $admin = $this->admin();
        $pending = $this->user('pending');
        $blocked = $this->user('blocked', ['email_verified_at' => null]);

        $this->actingAs($admin)->post("/admin/users/{$pending->id}/verify")->assertSessionHas('success');
        $this->actingAs($admin)->post("/admin/users/{$blocked->id}/verify")->assertSessionHas('success');

        $this->assertSame(User::STATUS_ACTIVE, $pending->fresh()->status);
        $this->assertNotNull($pending->fresh()->email_verified_at);
        $this->assertSame(User::STATUS_BLOCKED, $blocked->fresh()->status);
        $this->assertNotNull($blocked->fresh()->email_verified_at);
    }

    #[Group('accountStatus')]
    public function test_admin_unverify_returns_an_active_account_to_pending_and_keeps_other_statuses(): void
    {
        $admin = $this->admin();
        $active = $this->user('active');
        $blocked = $this->user('blocked');

        $this->actingAs($admin)->post("/admin/users/{$active->id}/unverify");
        $this->actingAs($admin)->post("/admin/users/{$blocked->id}/unverify");

        $this->assertSame(User::STATUS_PENDING, $active->fresh()->status);
        $this->assertNull($active->fresh()->email_verified_at);
        $this->assertSame(User::STATUS_BLOCKED, $blocked->fresh()->status);
        $this->assertNull($blocked->fresh()->email_verified_at);
    }

    #[Group('accountStatus')]
    public function test_resend_only_reaches_pending_accounts(): void
    {
        Notification::fake();
        $pending = $this->user('pending');
        $this->user('blocked', ['email_verified_at' => null]);

        $this->post('/email/verification-notification', ['email' => 'pending@example.test']);
        $this->post('/email/verification-notification', ['email' => 'blocked@example.test']);

        Notification::assertSentTo($pending, VerifyEmail::class);
        Notification::assertCount(1);
    }

    #[Group('accountStatus')]
    public function test_the_login_page_offers_a_resend_form_and_the_notice_page_carries_the_address(): void
    {
        $this->get('/login')->assertOk()->assertSee('Chưa nhận được email xác thực?')->assertSee(route('verification.send'), false);
    }

    #[Group('accountStatus')]
    public function test_the_user_list_shows_each_status(): void
    {
        $admin = $this->admin();
        foreach (['pending', 'inactive', 'blocked'] as $state) {
            $this->user($state);
        }

        $html = $this->actingAs($admin)->get('/admin/users')->assertOk()->getContent();

        foreach (['Hoạt động', 'Chờ xác thực', 'Ngưng hoạt động', 'Bị chặn'] as $label) {
            $this->assertStringContainsString($label, $html);
        }
    }

    #[Group('accountStatus')]
    public function test_the_edit_form_offers_the_four_statuses_with_the_current_one_selected(): void
    {
        $admin = $this->admin();
        $user = $this->user('blocked');

        $html = $this->actingAs($admin)->get("/admin/users/{$user->id}/edit")->assertOk()->getContent();

        $this->assertSame(4, substr_count($html, 'name="status"'));
        $this->assertMatchesRegularExpression('/name="status" value="4"[^>]*checked/', preg_replace('/\s+/', ' ', $html));
    }
}
