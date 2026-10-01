<?php

namespace Tests\Feature\Frontend\Controllers;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Attack simulations against password reset, e-mail verification and the admin login: Host-header poisoning,
 * account enumeration, token abuse, e-mail flooding, forged verification links and privilege probing.
 */
class PasswordResetAndVerificationSecurityTest extends TestCase
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

    // ── Host header poisoning ──────────────────────────────────────────────

    #[Group('authSecurity')]
    public function test_a_forged_host_header_cannot_redirect_the_password_reset_link_to_an_attacker(): void
    {
        Notification::fake();
        $victim = $this->user();

        $this->withServerVariables(['HTTP_HOST' => 'evil.test'])
            ->withHeaders(['X-Forwarded-Host' => 'evil.test'])
            ->post('/forgot-password', ['email' => 'victim@example.test'])->assertSessionHas('status');

        Notification::assertSentTo($victim, ResetPassword::class, function (ResetPassword $n) use ($victim) {
            $url = $n->toMail($victim)->actionUrl;

            $this->assertStringStartsWith(rtrim((string) config('app.url'), '/') . '/reset-password/', $url);
            $this->assertStringNotContainsString('evil.test', $url);

            return true;
        });
    }

    #[Group('authSecurity')]
    public function test_a_forged_host_header_cannot_redirect_the_verification_link_either(): void
    {
        Notification::fake();

        $this->withServerVariables(['HTTP_HOST' => 'evil.test'])->post('/register', [
            'username' => 'newuser', 'email' => 'new@example.test', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD,
        ])->assertRedirect();

        $user = User::where('email', 'new@example.test')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $n) use ($user) {
            $url = $n->toMail($user)->actionUrl;
            $this->assertStringNotContainsString('evil.test', $url);
            $this->assertStringStartsWith(rtrim((string) config('app.url'), '/'), $url);

            return true;
        });
    }

    #[Group('authSecurity')]
    public function test_generated_urls_and_redirects_are_pinned_to_the_configured_application_url(): void
    {
        $this->withServerVariables(['HTTP_HOST' => 'evil.test']);

        $this->assertStringStartsWith(rtrim((string) config('app.url'), '/'), url('/anything'));
        $this->assertStringStartsWith(rtrim((string) config('app.url'), '/'), route('login'));
        $this->assertStringStartsWith(rtrim((string) config('app.url'), '/'), URL::to('/x'));
    }

    // ── Forgot password ─────────────────────────────────────────────────────

    #[Group('authSecurity')]
    public function test_forgot_password_answers_identically_for_known_and_unknown_addresses(): void
    {
        Notification::fake();
        $this->user();

        $known = $this->post('/forgot-password', ['email' => 'victim@example.test']);
        $knownStatus = session('status');
        $unknown = $this->post('/forgot-password', ['email' => 'nobody@example.test']);

        $known->assertRedirect();
        $unknown->assertRedirect();
        $this->assertSame($knownStatus, session('status'));
        Notification::assertSentToTimes(User::where('email', 'victim@example.test')->first(), ResetPassword::class, 1);
        Notification::assertCount(1);   // nothing was sent to the stranger, and nothing said so
    }

    #[Group('authSecurity')]
    public function test_the_forgot_password_form_cannot_be_used_to_flood_one_inbox(): void
    {
        Notification::fake();
        $this->user();

        foreach (range(1, 5) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => "198.51.100.$i"])->post('/forgot-password', ['email' => 'victim@example.test'])->assertStatus(302);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.99'])->post('/forgot-password', ['email' => 'victim@example.test'])->assertStatus(429);
    }

    #[Group('authSecurity')]
    public function test_forgot_password_is_limited_to_three_a_minute_per_address(): void
    {
        Notification::fake();

        foreach (range(1, 3) as $i) {
            $this->post('/forgot-password', ['email' => "user$i@example.test"])->assertStatus(302);
        }

        $this->post('/forgot-password', ['email' => 'user4@example.test'])->assertStatus(429);
    }

    #[Group('authSecurity')]
    public function test_forgot_password_rejects_sql_arrays_and_oversized_input_without_sending_anything(): void
    {
        Notification::fake();
        $this->user();

        $this->post('/forgot-password', ['email' => "' OR 1=1 --@x.com"])->assertSessionHasErrors('email');
        $this->post('/forgot-password', ['email' => ['victim@example.test']])->assertSessionHasErrors('email');
        $this->post('/forgot-password', ['email' => str_repeat('a', 300) . '@example.test'])->assertSessionHasErrors('email');

        Notification::assertNothingSent();
    }

    // ── Reset password ──────────────────────────────────────────────────────

    private function resetToken(User $user): string
    {
        return Password::createToken($user);
    }

    #[Group('authSecurity')]
    public function test_a_valid_token_resets_the_password_once_and_only_once(): void
    {
        $user = $this->user();
        $token = $this->resetToken($user);
        $payload = ['token' => $token, 'email' => 'victim@example.test', 'password' => 'Brand-new-pass-7', 'password_confirmation' => 'Brand-new-pass-7'];

        $this->post('/reset-password', $payload)->assertRedirect('/login');
        $this->assertTrue(Hash::check('Brand-new-pass-7', $user->fresh()->password));

        $this->post('/reset-password', ['password' => 'Another-pass-88', 'password_confirmation' => 'Another-pass-88'] + $payload)->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('Brand-new-pass-7', $user->fresh()->password));   // replaying the token changed nothing
    }

    #[Group('authSecurity')]
    public function test_a_guessed_or_injected_token_never_resets_anything(): void
    {
        $user = $this->user();
        $hash = $user->password;

        foreach (['deadbeef', "' OR '1'='1", str_repeat('a', 64), '%', '../../etc/passwd'] as $token) {
            $this->post('/reset-password', ['token' => $token, 'email' => 'victim@example.test', 'password' => 'Hacked-pass-123', 'password_confirmation' => 'Hacked-pass-123'])
                ->assertSessionHasErrors('email');
        }

        $this->assertSame($hash, $user->fresh()->password);
    }

    #[Group('authSecurity')]
    public function test_a_token_for_one_account_cannot_reset_another_account(): void
    {
        $victim = $this->user();
        $attacker = $this->user(['email' => 'attacker@example.test']);
        $attackersToken = $this->resetToken($attacker);

        $this->post('/reset-password', ['token' => $attackersToken, 'email' => 'victim@example.test', 'password' => 'Hacked-pass-123', 'password_confirmation' => 'Hacked-pass-123'])
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check(self::PASSWORD, $victim->fresh()->password));
    }

    #[Group('authSecurity')]
    public function test_reset_answers_identically_for_an_unknown_account_and_a_wrong_token(): void
    {
        $this->user();

        $wrongToken = $this->post('/reset-password', ['token' => 'nope', 'email' => 'victim@example.test', 'password' => 'Brand-new-pass-7', 'password_confirmation' => 'Brand-new-pass-7']);
        $wrongTokenMessage = session('errors')->first('email');
        $unknown = $this->post('/reset-password', ['token' => 'nope', 'email' => 'nobody@example.test', 'password' => 'Brand-new-pass-7', 'password_confirmation' => 'Brand-new-pass-7']);

        $wrongToken->assertSessionHasErrors('email');
        $this->assertSame($wrongTokenMessage, session('errors')->first('email'));
        $this->assertStringNotContainsString('Không tìm thấy tài khoản', $wrongTokenMessage);
    }

    #[Group('authSecurity')]
    public function test_reset_enforces_the_password_policy_and_does_not_burn_the_token_on_a_weak_password(): void
    {
        $user = $this->user();
        $token = $this->resetToken($user);

        foreach (['short1', 'onlyletterspassword', '1234567890123', str_repeat('a1', 70)] as $weak) {
            $this->post('/reset-password', ['token' => $token, 'email' => 'victim@example.test', 'password' => $weak, 'password_confirmation' => $weak])->assertSessionHasErrors('password');
        }

        $this->post('/reset-password', ['token' => $token, 'email' => 'victim@example.test', 'password' => 'Strong-enough-1', 'password_confirmation' => 'Strong-enough-1'])->assertRedirect('/login');
    }

    #[Group('authSecurity')]
    public function test_reset_rotates_the_remember_token_and_verifies_a_previously_unverified_mailbox(): void
    {
        $user = $this->user(['email_verified_at' => null, 'remember_token' => 'stolen-remember-token']);
        $token = $this->resetToken($user);

        $this->post('/reset-password', ['token' => $token, 'email' => 'victim@example.test', 'password' => 'Brand-new-pass-7', 'password_confirmation' => 'Brand-new-pass-7'])->assertRedirect('/login');

        $fresh = $user->fresh();
        $this->assertNotSame('stolen-remember-token', $fresh->remember_token);
        $this->assertNotNull($fresh->email_verified_at);
    }

    #[Group('authSecurity')]
    public function test_the_reset_token_is_stored_hashed_not_in_clear(): void
    {
        $user = $this->user();
        $token = $this->resetToken($user);

        $stored = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');

        $this->assertNotSame($token, $stored);
        $this->assertTrue(Hash::check($token, $stored));
    }

    #[Group('authSecurity')]
    public function test_a_reset_token_expires(): void
    {
        $user = $this->user();
        $token = $this->resetToken($user);

        $this->travel(61)->minutes();

        $this->post('/reset-password', ['token' => $token, 'email' => 'victim@example.test', 'password' => 'Brand-new-pass-7', 'password_confirmation' => 'Brand-new-pass-7'])->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check(self::PASSWORD, $user->fresh()->password));
    }

    // ── E-mail verification ────────────────────────────────────────────────

    private function link(User $user, ?string $hash = null, ?int $id = null): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $id ?? $user->getKey(),
            'hash' => $hash ?? sha1($user->getEmailForVerification()),
        ]);
    }

    #[Group('authSecurity')]
    public function test_a_signed_link_verifies_the_account_without_needing_a_session_and_then_login_works(): void
    {
        $user = $this->user(['email_verified_at' => null]);

        $this->get($this->link($user))->assertRedirect(route('login'));

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->post('/login', ['email' => 'victim@example.test', 'password' => self::PASSWORD])->assertRedirect();
        $this->assertAuthenticated();
    }

    #[Group('authSecurity')]
    public function test_a_tampered_expired_or_unsigned_verification_link_is_refused(): void
    {
        $user = $this->user(['email_verified_at' => null]);
        $good = $this->link($user);

        $this->get(route('verification.verify', ['id' => $user->id, 'hash' => sha1($user->email)]))->assertStatus(403);   // no signature at all
        $this->get($good . '&extra=1')->assertStatus(403);                                                                    // signature no longer matches
        $this->get(preg_replace('/signature=[a-f0-9]+/', 'signature=' . str_repeat('0', 64), $good))->assertStatus(403);      // forged signature

        $this->travel(61)->minutes();
        $this->get($good)->assertStatus(403);                                                                                   // expired

        $this->assertNull($user->fresh()->email_verified_at);
    }

    #[Group('authSecurity')]
    public function test_a_link_cannot_verify_a_different_account_or_a_different_email_address(): void
    {
        $mine = $this->user(['email_verified_at' => null]);
        $theirs = $this->user(['email' => 'other@example.test', 'email_verified_at' => null]);

        // my hash but their id (the signature is valid because I can sign anything the server hands me... for MY link only)
        $this->get($this->link($mine, id: $theirs->id))->assertStatus(403);
        // a link issued for an old address stops working once the address changed
        $oldHash = sha1($mine->email);
        $mine->forceFill(['email' => 'changed@example.test'])->save();
        $this->get($this->link($mine, hash: $oldHash))->assertStatus(403);

        $this->assertNull($theirs->fresh()->email_verified_at);
        $this->assertNull($mine->fresh()->email_verified_at);
        $this->get(route('verification.verify', ['id' => 999999, 'hash' => 'x']))->assertStatus(403);
    }

    #[Group('authSecurity')]
    public function test_verifying_twice_is_harmless_and_the_non_numeric_id_is_not_routable(): void
    {
        $user = $this->user(['email_verified_at' => null]);
        $this->get($this->link($user))->assertRedirect();
        $first = $user->fresh()->email_verified_at;

        $this->get($this->link($user))->assertRedirect();

        $this->assertEquals($first, $user->fresh()->email_verified_at);
        $this->get('/email/verify/abc/def')->assertNotFound();
    }

    #[Group('authSecurity')]
    public function test_resending_the_verification_mail_needs_a_session_and_is_limited_to_three_a_minute(): void
    {
        Notification::fake();
        $user = $this->user(['email_verified_at' => null]);

        $this->post('/email/verification-notification')->assertRedirect('/login');

        foreach (range(1, 3) as $i) {
            $this->actingAs($user)->post('/email/verification-notification')->assertStatus(302);
        }
        $this->actingAs($user)->post('/email/verification-notification')->assertStatus(429);
        Notification::assertSentToTimes($user, VerifyEmail::class, 3);
    }

    // ── Admin login ─────────────────────────────────────────────────────────

    private function admin(): User
    {
        $admin = $this->user(['email' => 'boss@example.test']);
        $admin->assignRole(Role::ADMIN);

        return $admin;
    }

    #[Group('authSecurity')]
    public function test_the_admin_area_redirects_guests_and_turns_ordinary_users_away(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->get('/admin/users')->assertRedirect(route('admin.login'));
        $this->get('/admin/queue')->assertRedirect(route('admin.login'));

        $ordinary = $this->user();
        $ordinary->assignRole(Role::USER);
        $this->actingAs($ordinary)->get('/admin')->assertRedirect(route('home'));
        $this->actingAs($ordinary)->get('/admin/users')->assertRedirect(route('home'));
    }

    #[Group('authSecurity')]
    public function test_an_ordinary_user_who_knows_the_right_password_learns_nothing_from_the_admin_login(): void
    {
        $user = $this->user();
        $user->assignRole(Role::USER);

        $right = $this->post('/admin/login', ['email' => 'victim@example.test', 'password' => self::PASSWORD]);
        $rightMessage = session('errors')->first('email');
        $wrong = $this->post('/admin/login', ['email' => 'victim@example.test', 'password' => 'Wrong-password-1']);

        $right->assertSessionHasErrors('email');
        $this->assertSame($rightMessage, session('errors')->first('email'));
        $this->assertSame('Thông tin đăng nhập không chính xác.', $rightMessage);
        $this->assertGuest();
        $wrong->assertSessionHasErrors('email');
    }

    #[Group('authSecurity')]
    public function test_an_administrator_can_sign_in_and_land_on_the_dashboard(): void
    {
        $this->admin();

        $this->post('/admin/login', ['email' => 'boss@example.test', 'password' => self::PASSWORD])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated();
    }

    #[Group('authSecurity')]
    public function test_the_admin_login_is_protected_against_sql_injection_brute_force_and_enumeration(): void
    {
        $this->admin();

        foreach (["admin' OR '1'='1", "boss@example.test' --", "' OR 1=1--"] as $payload) {
            $this->post('/admin/login', ['email' => $payload, 'password' => $payload])->assertSessionHasErrors('email');
            $this->assertGuest();
        }

        foreach (range(1, 5) as $i) {
            $this->post('/admin/login', ['email' => 'boss@example.test', 'password' => "wrong-guess-$i"])->assertStatus(302);
        }
        $this->post('/admin/login', ['email' => 'boss@example.test', 'password' => self::PASSWORD])->assertStatus(429);
    }

    #[Group('authSecurity')]
    public function test_admin_login_and_frontend_login_share_the_same_lockout_for_one_account(): void
    {
        $this->admin();

        foreach (range(1, 5) as $i) {
            $this->post('/login', ['email' => 'boss@example.test', 'password' => "wrong-guess-$i"])->assertStatus(302);
        }

        // switching to the other door does not hand the attacker a fresh set of guesses
        $this->post('/admin/login', ['email' => 'boss@example.test', 'password' => self::PASSWORD])->assertStatus(429);
    }
}
