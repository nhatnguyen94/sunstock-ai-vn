<?php

namespace Tests\Feature\Frontend\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Models\UserProfile;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Attack simulations against login and registration: CSRF, SQL injection, brute force (incl. spoofed and
 * distributed sources), account enumeration, mass assignment, stored/reflected XSS, host-header poisoning,
 * session handling and the browser-facing security headers. Every test describes the attack in its name.
 */
class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Correct-horse-9';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function user(array $attrs = []): User
    {
        return User::factory()->create($attrs + ['email' => 'victim@example.test', 'password' => Hash::make(self::PASSWORD)]);
    }

    /** @return array<int, array{string}> */
    public static function sqlInjectionPayloads(): array
    {
        return array_map(fn ($p) => [$p], [
            "admin' OR '1'='1",
            "' OR 1=1--",
            "x@y.com'; DROP TABLE users;--",
            "victim@example.test' -- -",
            "\" OR \"\"=\"",
            "victim@example.test') OR ('1'='1",
            "1; SELECT SLEEP(5)",
            "' UNION SELECT 1,2,3,4,5,6--",
        ]);
    }

    // ── CSRF ────────────────────────────────────────────────────────────────

    /** @return array<string, array{string, string}> */
    public static function credentialPosts(): array
    {
        return [
            'login' => ['/login', 'post'],
            'register' => ['/register', 'post'],
            'forgot password' => ['/forgot-password', 'post'],
            'reset password' => ['/reset-password', 'post'],
            'admin login' => ['/admin/login', 'post'],
            'logout' => ['/logout', 'post'],
            'resend verification' => ['/email/verification-notification', 'post'],
        ];
    }

    #[Group('authSecurity')]
    #[DataProvider('credentialPosts')]
    public function test_a_cross_site_post_without_the_csrf_token_is_rejected(string $path): void
    {
        $this->app['env'] = 'local';   // the CSRF middleware is skipped while APP_ENV=testing

        $this->actingAs($this->user())->post($path, ['email' => 'victim@example.test', 'password' => self::PASSWORD])->assertStatus(419);
    }

    #[Group('authSecurity')]
    public function test_a_forged_csrf_token_is_rejected_and_a_valid_one_is_accepted(): void
    {
        $this->app['env'] = 'local';
        $this->user();

        $this->withSession(['_token' => 'real-token'])->post('/login', ['_token' => 'forged', 'email' => 'victim@example.test', 'password' => self::PASSWORD])->assertStatus(419);
        $this->assertGuest();

        $this->withSession(['_token' => 'real-token'])->post('/login', ['_token' => 'real-token', 'email' => 'victim@example.test', 'password' => self::PASSWORD])->assertRedirect();
        $this->assertAuthenticated();
    }

    #[Group('authSecurity')]
    public function test_logout_cannot_be_triggered_by_a_get_request(): void
    {
        $this->actingAs($this->user())->get('/logout')->assertStatus(405);
        $this->assertAuthenticated();
    }

    // ── SQL injection ───────────────────────────────────────────────────────

    #[Group('authSecurity')]
    #[DataProvider('sqlInjectionPayloads')]
    public function test_sql_injection_in_the_login_form_never_authenticates_or_breaks_the_query(string $payload): void
    {
        $this->user();

        foreach ([['email' => $payload, 'password' => $payload], ['email' => 'victim@example.test', 'password' => $payload]] as $data) {
            $this->post('/login', $data)->assertRedirect()->assertSessionHasErrors('email');
            $this->assertGuest();
        }

        $this->assertSame(1, User::count());   // the table is still there and untouched
    }

    #[Group('authSecurity')]
    public function test_array_and_object_shaped_inputs_are_refused_not_passed_to_the_query_builder(): void
    {
        $this->user();

        $this->post('/login', ['email' => ['victim@example.test'], 'password' => self::PASSWORD])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'victim@example.test', 'password' => [self::PASSWORD]])->assertSessionHasErrors('password');
        $this->postJson('/login', ['email' => ['$ne' => null], 'password' => ['$ne' => null]])->assertStatus(422);
        $this->assertGuest();
    }

    #[Group('authSecurity')]
    public function test_sql_in_the_registration_username_is_rejected_by_the_whitelist(): void
    {
        $this->post('/register', $this->registration(['username' => "x'),('y"]))->assertSessionHasErrors('username');
        $this->post('/register', $this->registration(['username' => "bob'; DROP TABLE users;--"]))->assertSessionHasErrors('username');
        $this->assertSame(0, User::count());
    }

    // ── Login behaviour ─────────────────────────────────────────────────────

    #[Group('authSecurity')]
    public function test_a_correct_login_works_and_ignores_attacker_supplied_redirect_parameters(): void
    {
        $this->user();

        $this->post('/login?redirect=https://evil.test&next=https://evil.test', [
            'email' => 'victim@example.test', 'password' => self::PASSWORD, 'redirect' => 'https://evil.test', 'intended' => 'https://evil.test',
        ])->assertRedirect(url('/'));

        $this->assertAuthenticated();
    }

    #[Group('authSecurity')]
    public function test_the_email_is_case_and_whitespace_insensitive_on_login(): void
    {
        $this->user();

        $this->post('/login', ['email' => '  VICTIM@Example.TEST ', 'password' => self::PASSWORD])->assertRedirect();
        $this->assertAuthenticated();
    }

    #[Group('authSecurity')]
    public function test_an_unknown_email_and_a_wrong_password_give_the_same_answer(): void
    {
        $this->user();

        $wrongPassword = $this->post('/login', ['email' => 'victim@example.test', 'password' => 'nope-nope-1']);
        $unknown = $this->post('/login', ['email' => 'nobody@example.test', 'password' => 'nope-nope-1']);

        $wrongPassword->assertSessionHasErrors('email');
        $this->assertSame(session('errors')->first('email'), $this->errorOf($unknown));
        $this->assertSame('Thông tin đăng nhập không chính xác.', $this->errorOf($unknown));
    }

    private function errorOf($response): string
    {
        return $response->baseResponse->getSession()->get('errors')->first('email');
    }

    #[Group('authSecurity')]
    public function test_an_unverified_account_gets_no_session_and_no_remember_cookie_even_with_the_right_password(): void
    {
        $this->user(['email_verified_at' => null]);

        $response = $this->post('/login', ['email' => 'victim@example.test', 'password' => self::PASSWORD, 'remember' => '1']);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertEmpty(array_filter($response->headers->getCookies(), fn ($c) => str_starts_with($c->getName(), 'remember_web')));
        $this->assertStringContainsString('xác thực email', $this->errorOf($response));
    }

    #[Group('authSecurity')]
    public function test_a_wrong_password_for_an_unverified_account_does_not_reveal_that_it_is_unverified(): void
    {
        $this->user(['email_verified_at' => null]);

        $response = $this->post('/login', ['email' => 'victim@example.test', 'password' => 'wrong-pass-1']);

        $this->assertSame('Thông tin đăng nhập không chính xác.', $this->errorOf($response));
    }

    #[Group('authSecurity')]
    public function test_a_huge_password_is_refused_before_it_reaches_the_hasher(): void
    {
        $this->user();

        $this->post('/login', ['email' => 'victim@example.test', 'password' => str_repeat('A', 1_000_000)])->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    #[Group('authSecurity')]
    public function test_session_id_changes_on_login_so_a_planted_session_cannot_be_reused(): void
    {
        $this->user();
        $before = $this->app['session.store']->getId();

        $this->post('/login', ['email' => 'victim@example.test', 'password' => self::PASSWORD])->assertRedirect();

        $this->assertNotSame($before, $this->app['session.store']->getId());
    }

    #[Group('authSecurity')]
    public function test_logout_destroys_the_session_data(): void
    {
        $this->actingAs($this->user())->withSession(['secret' => 'x'])->post('/logout')->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull(session('secret'));
    }

    // ── Brute force ─────────────────────────────────────────────────────────

    #[Group('authSecurity')]
    public function test_the_sixth_guess_at_one_account_from_one_address_in_a_minute_is_blocked_even_with_the_right_password(): void
    {
        $this->user();

        foreach (range(1, 5) as $i) {
            $this->post('/login', ['email' => 'victim@example.test', 'password' => "wrong-guess-$i"])->assertStatus(302);
        }

        $this->post('/login', ['email' => 'victim@example.test', 'password' => self::PASSWORD])->assertStatus(429);
        $this->assertGuest();
    }

    #[Group('authSecurity')]
    public function test_lockout_cannot_be_dodged_by_changing_the_case_of_the_email_or_spoofing_forwarding_headers(): void
    {
        $this->user();

        foreach (range(1, 5) as $i) {
            $email = $i % 2 ? 'victim@example.test' : 'VICTIM@Example.Test';
            $this->withHeaders(['X-Forwarded-For' => "203.0.113.$i", 'X-Real-IP' => "203.0.113.$i"])
                ->post('/login', ['email' => $email, 'password' => "wrong-guess-$i"])->assertStatus(302);
        }

        $this->withHeaders(['X-Forwarded-For' => '198.51.100.7'])
            ->post('/login', ['email' => 'victim@example.test', 'password' => self::PASSWORD])->assertStatus(429);
    }

    #[Group('authSecurity')]
    public function test_password_spraying_many_accounts_from_one_address_is_stopped_at_twenty_a_minute(): void
    {
        foreach (range(1, 20) as $i) {
            $this->post('/login', ['email' => "user$i@example.test", 'password' => 'Spring2026!'])->assertStatus(302);
        }

        $this->post('/login', ['email' => 'user21@example.test', 'password' => 'Spring2026!'])->assertStatus(429);
    }

    #[Group('authSecurity')]
    public function test_a_distributed_attack_on_one_account_is_capped_per_hour_whatever_the_source_address(): void
    {
        $this->user();

        foreach (range(1, 30) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => "198.51.100.$i"])
                ->post('/login', ['email' => 'victim@example.test', 'password' => "guess-$i-abc"])->assertStatus(302);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.99'])
            ->post('/login', ['email' => 'victim@example.test', 'password' => self::PASSWORD])->assertStatus(429);
        $this->assertGuest();
    }

    #[Group('authSecurity')]
    public function test_one_persons_failed_attempts_do_not_lock_out_a_different_account(): void
    {
        $this->user();
        $this->user(['email' => 'other@example.test']);

        foreach (range(1, 6) as $i) {
            $this->post('/login', ['email' => 'victim@example.test', 'password' => "wrong-$i-guess"]);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
            ->post('/login', ['email' => 'other@example.test', 'password' => self::PASSWORD])->assertRedirect();
        $this->assertAuthenticated();
    }

    #[Group('authSecurity')]
    public function test_just_viewing_the_forms_never_uses_up_the_login_budget(): void
    {
        $this->user();

        foreach (range(1, 12) as $i) {
            $this->get('/login')->assertOk();
            $this->get('/register')->assertOk();
        }

        $this->post('/login', ['email' => 'victim@example.test', 'password' => self::PASSWORD])->assertRedirect();
        $this->assertAuthenticated();
    }

    #[Group('authSecurity')]
    public function test_registration_is_limited_to_five_a_minute_per_address(): void
    {
        Notification::fake();

        foreach (range(1, 5) as $i) {
            $this->post('/register', $this->registration(['username' => "bot$i", 'email' => "bot$i@example.test"]))->assertRedirect('/login');
        }

        $this->post('/register', $this->registration(['username' => 'bot6', 'email' => 'bot6@example.test']))->assertStatus(429);
        $this->assertSame(5, User::count());
    }

    // ── Registration ────────────────────────────────────────────────────────

    /** @param array<string, mixed> $over */
    private function registration(array $over = []): array
    {
        return $over + [
            'username' => 'newuser',
            'email' => 'new@example.test',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ];
    }

    #[Group('authSecurity')]
    public function test_a_normal_registration_creates_an_unverified_user_with_the_user_role_and_sends_the_verification_mail(): void
    {
        Notification::fake();

        $this->post('/register', $this->registration(['username' => 'Nguyễn Văn A', 'mobile' => '+84 912 345 678']))->assertRedirect('/login');

        $user = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertTrue($user->hasRole(Role::USER));
        $this->assertSame('Nguyễn Văn A', $user->profile->username);
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));
        $this->assertNotSame(self::PASSWORD, $user->password);
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->assertGuest();   // not signed in before the address is confirmed
    }

    #[Group('authSecurity')]
    public function test_mass_assignment_cannot_grant_roles_verification_or_a_chosen_id(): void
    {
        Notification::fake();

        $this->post('/register', $this->registration([
            'email_verified_at' => '2020-01-01 00:00:00', 'role' => 'admin', 'roles' => ['admin'], 'is_admin' => 1, 'id' => 1, 'remember_token' => 'x',
        ]))->assertRedirect('/login');

        $user = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertSame([Role::USER], $user->roles->pluck('name')->all());
        $this->assertFalse($user->canAccessBackend());
        $this->assertNull($user->remember_token);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function badRegistrations(): array
    {
        return [
            'script tag as username' => [['username' => '<script>alert(1)</script>'], 'username'],
            'img onerror as username' => [['username' => '<img src=x onerror=alert(1)>'], 'username'],
            'quote breaking out of an attribute' => [['username' => '" onmouseover="alert(1)'], 'username'],
            'javascript scheme' => [['username' => 'javascript:alert(1)//'], 'username'],
            'username too short' => [['username' => 'ab'], 'username'],
            'username 101 chars' => [['username' => str_repeat('a', 101)], 'username'],
            'html in mobile' => [['mobile' => '<b>1</b>'], 'mobile'],
            'letters in mobile' => [['mobile' => 'call-me-maybe'], 'mobile'],
            'seven char password' => [['password' => 'Abcde1f', 'password_confirmation' => 'Abcde1f'], 'password'],
            'password without a digit' => [['password' => 'abcdefghij', 'password_confirmation' => 'abcdefghij'], 'password'],
            'password without a letter' => [['password' => '1234567890', 'password_confirmation' => '1234567890'], 'password'],
            'password over 128 chars' => [['password' => str_repeat('a1', 70), 'password_confirmation' => str_repeat('a1', 70)], 'password'],
            'megabyte password' => [['password' => str_repeat('a1', 500_000), 'password_confirmation' => str_repeat('a1', 500_000)], 'password'],
            'confirmation mismatch' => [['password_confirmation' => 'Different-9'], 'password'],
            'email without domain' => [['email' => 'nobody'], 'email'],
            'email as array' => [['email' => ['a@b.test']], 'email'],
            'email 300 chars' => [['email' => str_repeat('a', 300) . '@example.test'], 'email'],
        ];
    }

    #[Group('authSecurity')]
    #[DataProvider('badRegistrations')]
    public function test_malicious_or_malformed_registration_fields_are_rejected(array $override, string $field): void
    {
        Notification::fake();

        $this->post('/register', $this->registration($override))->assertSessionHasErrors($field);

        $this->assertSame(0, User::count());
        $this->assertSame(0, UserProfile::count());
    }

    #[Group('authSecurity')]
    public function test_registering_an_existing_email_in_another_case_is_a_duplicate(): void
    {
        Notification::fake();
        $this->user();

        $this->post('/register', $this->registration(['email' => 'VICTIM@EXAMPLE.TEST']))->assertSessionHasErrors('email');
        $this->assertSame(1, User::count());
    }

    #[Group('authSecurity')]
    public function test_a_failure_halfway_through_registration_leaves_no_half_created_account(): void
    {
        Notification::fake();
        UserProfile::creating(fn () => throw new \RuntimeException('boom'));

        $this->post('/register', $this->registration())->assertSessionHasErrors('error');

        $this->assertSame(0, User::count());
    }

    // ── XSS: output encoding of stored and reflected values ────────────────

    #[Group('authSecurity')]
    public function test_a_hostile_stored_username_is_escaped_in_the_navbar_and_profile_page(): void
    {
        $evil = '<script>alert("xss")</script><img src=x onerror=alert(1)>';
        $user = $this->user(['name' => $evil]);
        UserProfile::create(['user_id' => $user->id, 'username' => $evil, 'bio' => $evil, 'address' => $evil]);

        foreach (['/profile', '/profile/edit'] as $page) {
            $r = $this->actingAs($user)->get($page)->assertOk();
            $r->assertDontSee('<script>alert("xss")</script>', false);
            $r->assertDontSee('<img src=x onerror=alert(1)>', false);
            $r->assertSee('&lt;script&gt;', false);
        }
    }

    #[Group('authSecurity')]
    public function test_old_input_and_reset_link_parameters_are_escaped_when_echoed_back(): void
    {
        $payload = '"><script>alert(1)</script>';

        $this->withSession(['_old_input' => ['email' => $payload, 'username' => $payload, 'mobile' => $payload]])->get('/login')->assertDontSee($payload, false);
        $this->withSession(['_old_input' => ['email' => $payload, 'username' => $payload, 'mobile' => $payload]])->get('/register')->assertDontSee($payload, false);
        $this->get('/reset-password/abc?email=' . urlencode($payload))->assertOk()->assertDontSee($payload, false)->assertSee('&quot;&gt;&lt;script&gt;', false);
        $this->get('/reset-password/' . rawurlencode('"><script>alert(1)</script>'))->assertDontSee('<script>alert(1)</script>', false);
    }

    // ── Headers ─────────────────────────────────────────────────────────────

    #[Group('authSecurity')]
    public function test_every_page_carries_the_clickjacking_and_sniffing_protections(): void
    {
        foreach (['/login', '/register', '/forgot-password', '/admin/login'] as $path) {
            $r = $this->get($path);
            $this->assertSame('SAMEORIGIN', $r->headers->get('X-Frame-Options'), $path);
            $this->assertSame('nosniff', $r->headers->get('X-Content-Type-Options'), $path);
            $this->assertStringContainsString("frame-ancestors 'self'", $r->headers->get('Content-Security-Policy'), $path);
            $this->assertStringContainsString("form-action 'self'", $r->headers->get('Content-Security-Policy'), $path);
            $this->assertStringContainsString("base-uri 'self'", $r->headers->get('Content-Security-Policy'), $path);
            $this->assertStringContainsString("object-src 'none'", $r->headers->get('Content-Security-Policy'), $path);
            $this->assertNotNull($r->headers->get('Referrer-Policy'), $path);
            $this->assertNotNull($r->headers->get('Permissions-Policy'), $path);
            $this->assertFalse($r->headers->has('X-Powered-By'), $path);
        }
    }

    #[Group('authSecurity')]
    public function test_forms_and_reset_links_are_never_cached_and_the_reset_token_does_not_leak_through_referer(): void
    {
        foreach (['/login', '/register', '/forgot-password', '/reset-password/sometoken', '/admin/login'] as $path) {
            $this->assertStringContainsString('no-store', $this->get($path)->headers->get('Cache-Control'), $path);
        }

        $this->assertSame('no-referrer', $this->get('/reset-password/sometoken')->headers->get('Referrer-Policy'));
    }

    #[Group('authSecurity')]
    public function test_hsts_is_sent_over_https_only(): void
    {
        $this->assertNotNull($this->get('https://localhost/login')->headers->get('Strict-Transport-Security'));
        $this->assertNull($this->get('http://localhost/login')->headers->get('Strict-Transport-Security'));
    }

    #[Group('authSecurity')]
    public function test_the_session_cookie_is_http_only_and_same_site(): void
    {
        $this->assertTrue(config('session.http_only'));
        $this->assertContains(config('session.same_site'), ['lax', 'strict']);
    }

    // ── Sessions after a password change ───────────────────────────────────

    #[Group('authSecurity')]
    public function test_a_session_created_before_a_password_change_is_signed_out(): void
    {
        $user = $this->user();

        // the thief's session still carries the hash of the OLD password
        $stale = Hash::make('the-old-password-1');
        $this->actingAs($user)->withSession(['password_hash_web' => $stale])->get('/profile')->assertRedirect('/login');
        $this->assertGuest();

        // ...while a session that matches the current password keeps working
        $this->actingAs($user)->withSession(['password_hash_web' => $user->getAuthPassword()])->get('/profile')->assertOk();
    }

    #[Group('authSecurity')]
    public function test_changing_the_password_in_the_profile_keeps_this_session_but_refreshes_its_hash(): void
    {
        $user = $this->user();
        UserProfile::create(['user_id' => $user->id, 'username' => 'victim']);

        $this->actingAs($user)->withSession(['password_hash_web' => $user->getAuthPassword()])
            ->put('/profile', ['username' => 'victim', 'current_password' => self::PASSWORD, 'password' => 'Brand-new-pass-7', 'password_confirmation' => 'Brand-new-pass-7'])
            ->assertRedirect('/profile');

        $fresh = $user->fresh();
        $this->assertTrue(Hash::check('Brand-new-pass-7', $fresh->password));

        // this device's session was re-stamped with the NEW password and keeps working...
        $stamp = session('password_hash_web');
        $this->assertNotEmpty($stamp);
        $this->actingAs($fresh)->withSession(['password_hash_web' => $stamp])->get('/profile')->assertOk();

        // ...while a session stamped with the old password (another device) is signed out
        $this->actingAs($fresh)->withSession(['password_hash_web' => Hash::make(self::PASSWORD)])->get('/profile')->assertRedirect('/login');
    }
}
