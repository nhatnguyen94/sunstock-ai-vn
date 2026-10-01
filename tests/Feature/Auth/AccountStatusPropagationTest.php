<?php

namespace Tests\Feature\Auth;

use App\Frontend\Repositories\PortfolioRepository;
use App\Models\Portfolio;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Where the account status (0 inactive, 1 active, 2 pending, 4 blocked) must be honoured beyond the login form:
 * remember-me cookies, password reset, scheduled work, e-mails, the admin dashboard and the admin user list.
 */
class AccountStatusPropagationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Correct-horse-9';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function user(string $email, string $state = 'active'): User
    {
        $factory = match ($state) {
            'pending' => User::factory()->unverified(),
            'inactive' => User::factory()->inactive(),
            'blocked' => User::factory()->blocked(),
            default => User::factory(),
        };

        return $factory->create(['email' => $email, 'password' => Hash::make(self::PASSWORD)]);
    }

    private function admin(): User
    {
        $u = $this->user('boss@example.test');
        $u->assignRole(Role::ADMIN);

        return $u->fresh();
    }

    // ── the single definition of "may enter" ───────────────────────────────

    #[Group('accountStatus')]
    public function test_the_may_enter_scope_matches_can_sign_in_for_every_combination(): void
    {
        $cases = [
            'ok@x.test' => $this->user('ok@x.test'),
            'pending@x.test' => $this->user('pending@x.test', 'pending'),
            'inactive@x.test' => $this->user('inactive@x.test', 'inactive'),
            'blocked@x.test' => $this->user('blocked@x.test', 'blocked'),
            'unconfirmed@x.test' => User::factory()->create(['email' => 'unconfirmed@x.test', 'email_verified_at' => null]),   // active but never confirmed
        ];

        $allowed = User::mayEnter()->pluck('email')->all();

        $this->assertSame(['ok@x.test'], $allowed);
        foreach ($cases as $email => $user) {
            $this->assertSame(in_array($email, $allowed, true), $user->fresh()->canSignIn(), $email);
        }
    }

    // ── remember-me: a stolen long-lived cookie must stop working too ───────

    #[Group('accountStatus')]
    public function test_a_remember_me_cookie_stops_working_when_the_account_is_blocked(): void
    {
        $user = $this->user('remember@x.test');

        $response = $this->post('/login', ['email' => 'remember@x.test', 'password' => self::PASSWORD, 'remember' => '1'])->assertRedirect();
        $cookie = collect($response->headers->getCookies())->first(fn ($c) => str_starts_with($c->getName(), 'remember_web'));
        $this->assertNotNull($cookie, 'precondition: the login handed out a remember cookie');

        // a brand new browser with nothing but the remember cookie signs in...
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue())->get('/profile')->assertOk();
        $this->assertAuthenticated();

        // ...until the account is blocked
        $user->forceFill(['status' => User::STATUS_BLOCKED])->save();
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue())->get('/profile')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    // ── public pages and AJAX for a session that was just ended ────────────

    #[Group('accountStatus')]
    public function test_a_blocked_session_is_ended_on_a_public_page_too_and_ajax_gets_json(): void
    {
        $blocked = $this->user('blocked@x.test', 'blocked');

        $this->actingAs($blocked)->get('/login')->assertRedirect(route('login'));
        $this->assertGuest();

        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->actingAs($blocked)->postJson('/ai-chat', ['message' => 'hi'])->assertStatus(401);
        $this->assertGuest();
    }

    #[Group('accountStatus')]
    public function test_a_blocked_administrator_gets_a_401_from_the_admin_polling_endpoints(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['status' => User::STATUS_BLOCKED])->save();

        $this->actingAs($admin->fresh())->getJson('/admin/queue/stats')->assertStatus(401);
        $this->assertGuest();
    }

    // ── registration cannot be used to get round a block ───────────────────

    #[Group('accountStatus')]
    public function test_a_blocked_persons_address_cannot_be_registered_again(): void
    {
        $this->user('blocked@x.test', 'blocked');

        $this->post('/register', ['username' => 'comeback', 'email' => 'BLOCKED@x.test', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])->assertSessionHasErrors('email');
        $this->assertSame(1, User::count());
    }

    // ── password reset ──────────────────────────────────────────────────────

    #[Group('accountStatus')]
    public function test_the_forgot_password_form_mails_active_and_pending_accounts_only_and_answers_the_same_for_all(): void
    {
        Notification::fake();
        $active = $this->user('active@x.test');
        $pending = $this->user('pending@x.test', 'pending');
        $this->user('inactive@x.test', 'inactive');
        $this->user('blocked@x.test', 'blocked');

        $answers = [];
        foreach (['active@x.test', 'pending@x.test', 'inactive@x.test', 'blocked@x.test', 'nobody@x.test'] as $i => $email) {
            $this->withServerVariables(['REMOTE_ADDR' => "198.51.100.$i"])->post('/forgot-password', ['email' => $email])->assertRedirect();
            $answers[] = session('status');
        }

        $this->assertCount(1, array_unique($answers));
        Notification::assertSentTo($active, ResetPassword::class);
        Notification::assertSentTo($pending, ResetPassword::class);
        Notification::assertCount(2);
    }

    #[Group('accountStatus')]
    public function test_a_reset_token_issued_before_the_account_was_blocked_is_refused(): void
    {
        foreach (['blocked' => User::STATUS_BLOCKED, 'inactive' => User::STATUS_INACTIVE] as $state => $status) {
            $user = $this->user("$state@x.test");
            $token = Password::createToken($user);
            $user->forceFill(['status' => $status])->save();
            $hash = $user->fresh()->password;

            $this->post('/reset-password', ['token' => $token, 'email' => "$state@x.test", 'password' => 'Brand-new-pass-7', 'password_confirmation' => 'Brand-new-pass-7'])->assertSessionHasErrors('email');

            $this->assertSame($hash, $user->fresh()->password, $state);
            $this->assertStringContainsString('không hợp lệ hoặc đã hết hạn', session('errors')->first('email'));
        }
    }

    #[Group('accountStatus')]
    public function test_a_pending_account_can_still_reset_its_password_and_becomes_active(): void
    {
        $user = $this->user('pending@x.test', 'pending');
        $token = Password::createToken($user);

        $this->post('/reset-password', ['token' => $token, 'email' => 'pending@x.test', 'password' => 'Brand-new-pass-7', 'password_confirmation' => 'Brand-new-pass-7'])->assertRedirect('/login');

        $this->assertSame(User::STATUS_ACTIVE, $user->fresh()->status);
        $this->post('/login', ['email' => 'pending@x.test', 'password' => 'Brand-new-pass-7'])->assertRedirect();
        $this->assertAuthenticated();
    }

    // ── background work ─────────────────────────────────────────────────────

    #[Group('accountStatus')]
    public function test_the_scheduled_portfolio_refresh_skips_owners_who_may_not_sign_in(): void
    {
        $ids = [];
        foreach (['active' => 'active', 'pending' => 'pending', 'inactive' => 'inactive', 'blocked' => 'blocked'] as $email => $state) {
            $owner = $this->user("$email@x.test", $state);
            $ids[$state] = Portfolio::create(['user_id' => $owner->id, 'name' => $state, 'total_invested' => 0, 'current_value' => 0, 'is_active' => true])->id;
        }
        $inactivePortfolio = Portfolio::create(['user_id' => User::where('email', 'active@x.test')->value('id'), 'name' => 'off', 'total_invested' => 0, 'current_value' => 0, 'is_active' => false]);

        $picked = (new PortfolioRepository)->getAllActivePortfolios()->pluck('id')->all();

        $this->assertSame([$ids['active']], $picked);
        $this->assertNotContains($inactivePortfolio->id, $picked);   // a portfolio switched off by its own flag is still skipped
    }

    // ── admin: dashboard and user list ──────────────────────────────────────

    #[Group('accountStatus')]
    public function test_the_dashboard_counts_follow_the_status_not_just_the_email(): void
    {
        $admin = $this->admin();
        $this->user('p1@x.test', 'pending');
        $this->user('p2@x.test', 'pending');
        $this->user('b1@x.test', 'blocked');
        $this->user('i1@x.test', 'inactive');
        $blockedAndUnconfirmed = User::factory()->blocked()->create(['email' => 'b2@x.test', 'email_verified_at' => null]);   // an unconfirmed address, but a block is what matters

        $html = $this->actingAs($admin)->get('/admin')->assertOk()->getContent();

        $this->assertStringContainsString('2 chưa xác thực email', $html);       // only the two pending
        $this->assertStringContainsString('Tài khoản bị chặn / ngưng', $html);
        $this->assertMatchesRegularExpression('/Tài khoản bị chặn \/ ngưng.*?>\s*3\s*</s', $html);   // blocked x2 + inactive x1
        $this->assertNotNull($blockedAndUnconfirmed);
    }

    #[Group('accountStatus')]
    public function test_the_user_list_can_be_filtered_by_status_and_search_does_not_escape_the_filter(): void
    {
        $admin = $this->admin();
        $this->user('anna.active@x.test');
        $this->user('anna.blocked@x.test', 'blocked');
        $this->user('bob.blocked@x.test', 'blocked');
        $this->user('carl.pending@x.test', 'pending');

        $blocked = $this->actingAs($admin)->get('/admin/users?status=4')->assertOk()->getContent();
        $this->assertStringContainsString('anna.blocked@x.test', $blocked);
        $this->assertStringContainsString('bob.blocked@x.test', $blocked);
        $this->assertStringNotContainsString('anna.active@x.test', $blocked);
        $this->assertStringNotContainsString('carl.pending@x.test', $blocked);

        // "anna" matches two people, but combined with status=4 only the blocked one may remain
        $both = $this->actingAs($admin)->get('/admin/users?search=anna&status=4')->assertOk()->getContent();
        $this->assertStringContainsString('anna.blocked@x.test', $both);
        $this->assertStringNotContainsString('anna.active@x.test', $both);
        $this->assertStringNotContainsString('bob.blocked@x.test', $both);

        $pending = $this->actingAs($admin)->get('/admin/users?status=2')->getContent();
        $this->assertStringContainsString('carl.pending@x.test', $pending);
        $this->assertStringNotContainsString('anna.blocked@x.test', $pending);
    }

    #[Group('accountStatus')]
    public function test_the_status_filter_ignores_nonsense_and_status_zero_is_a_real_filter(): void
    {
        $admin = $this->admin();
        $this->user('off@x.test', 'inactive');
        $this->user('on@x.test');

        foreach (['abc', '3', '99', '-1', '', '1; DROP TABLE users'] as $junk) {
            $html = $this->actingAs($admin)->get('/admin/users?status='.urlencode($junk))->assertOk()->getContent();
            $this->assertStringContainsString('off@x.test', $html, "status=$junk must not filter anything");
            $this->assertStringContainsString('on@x.test', $html);
        }

        $zero = $this->actingAs($admin)->get('/admin/users?status=0')->getContent();
        $this->assertStringContainsString('off@x.test', $zero);
        $this->assertStringNotContainsString('on@x.test', $zero);
    }

    #[Group('accountStatus')]
    public function test_the_filter_form_offers_every_status_and_keeps_the_selection(): void
    {
        $html = $this->actingAs($this->admin())->get('/admin/users?status=4')->assertOk()->getContent();

        foreach (['Mọi trạng thái', 'Hoạt động', 'Chờ xác thực', 'Ngưng hoạt động', 'Bị chặn'] as $label) {
            $this->assertStringContainsString($label, $html);
        }
        $this->assertMatchesRegularExpression('/<option value="4"\s+selected>/', $html);
    }
}
