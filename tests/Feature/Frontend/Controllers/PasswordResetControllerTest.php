<?php

namespace Tests\Feature\Frontend\Controllers;

use App\Frontend\Controllers\PasswordResetController;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * "Feature" because these go through real HTTP + validation (needs the
 * booted container). No database is touched.
 *
 * The two "show form" tests call the controller method directly instead of
 * a real GET request: every page extends layouts.app, whose navbar queries
 * NewsCategory — a table that doesn't exist in the sqlite test DB (see
 * docs/TESTING.md). Calling the controller directly returns the View
 * object without ->render()-ing the layout, so the navbar query never runs.
 *
 * The validation-failure tests ARE real HTTP requests: a failed
 * $request->validate() redirects back (302) rather than rendering a view,
 * so they never hit the layout either — and they're what exercise the
 * actual routes end-to-end.
 *
 * Not covered here for the same DB reason: the "happy path" round-trip
 * (valid email finds a user / valid token actually resets a password).
 */
class PasswordResetControllerTest extends TestCase
{
    #[Group('auth')]
    public function test_forgot_password_form_returns_the_correct_view(): void
    {
        $view = (new PasswordResetController())->showForgotForm();

        $this->assertSame('auth.forgot-password', $view->getName());
    }

    #[Group('auth')]
    public function test_reset_password_form_returns_the_correct_view_with_token_and_email(): void
    {
        $request = Request::create('/reset-password/some-token', 'GET', ['email' => 't@example.com']);

        $view = (new PasswordResetController())->showResetForm($request, 'some-token');

        $this->assertSame('auth.reset-password', $view->getName());
        $this->assertSame('some-token', $view->getData()['token']);
        $this->assertSame('t@example.com', $view->getData()['email']);
    }

    #[Group('auth')]
    public function test_forgot_password_requires_an_email(): void
    {
        $this->post('/forgot-password', [])
            ->assertSessionHasErrors('email');
    }

    #[Group('auth')]
    public function test_forgot_password_rejects_a_malformed_email(): void
    {
        $this->post('/forgot-password', ['email' => 'not-an-email'])
            ->assertSessionHasErrors('email');
    }

    #[Group('auth')]
    public function test_reset_password_requires_token_email_and_password(): void
    {
        $this->post('/reset-password', [])
            ->assertSessionHasErrors(['token', 'email', 'password']);
    }

    #[Group('auth')]
    public function test_reset_password_rejects_a_short_password(): void
    {
        $this->post('/reset-password', [
            'token' => 'some-token',
            'email' => 't@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
    }

    #[Group('auth')]
    public function test_reset_password_rejects_mismatched_confirmation(): void
    {
        $this->post('/reset-password', [
            'token' => 'some-token',
            'email' => 't@example.com',
            'password' => 'longenoughpassword',
            'password_confirmation' => 'somethingelse',
        ])->assertSessionHasErrors('password');
    }
}
