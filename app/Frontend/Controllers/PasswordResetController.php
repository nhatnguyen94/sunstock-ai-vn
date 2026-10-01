<?php

namespace App\Frontend\Controllers;

use App\Models\User;
use App\Support\AuthRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    /**
     * Show the "forgot password" form.
     */
    public function showForgotForm()
    {
        return view('auth.forgot-password');
    }

    /**
     * Send a password-reset link to the given email, if it exists.
     */
    public function sendResetLink(Request $request)
    {
        $request->merge(['email' => AuthRules::normalizeEmail($request->input('email'))]);

        $request->validate([
            'email' => 'required|string|email|max:255',
        ], [
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không hợp lệ.',
        ]);

        Password::sendResetLink($request->only('email'));

        // Deliberately the SAME message regardless of whether the broker
        // actually found a matching account — telling the user "email not
        // found" here would let anyone enumerate registered addresses.
        return back()->with('status', 'Nếu email này có trong hệ thống, chúng tôi đã gửi link đặt lại mật khẩu tới đó. Vui lòng kiểm tra hộp thư (và cả thư mục spam).');
    }

    /**
     * Show the "set a new password" form.
     */
    public function showResetForm(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    /**
     * Set a new password using a valid reset token.
     */
    public function reset(Request $request)
    {
        $request->merge(['email' => AuthRules::normalizeEmail($request->input('email'))]);

        $request->validate([
            'token' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => ['required', 'string', AuthRules::password(), 'confirmed'],
        ], AuthRules::messages() + [
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không hợp lệ.',
            'password.required' => 'Vui lòng nhập mật khẩu mới.',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->password = Hash::make($password);
                $user->setRememberToken(Str::random(60));
                // Receiving the reset mail proves the mailbox is theirs, so a pending account becomes active.
                // Blocked / switched-off accounts stay as they are: a reset must never undo an admin's decision.
                if ($user->status === User::STATUS_PENDING) {
                    $user->applyStatus(User::STATUS_ACTIVE);
                }
                $user->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('success', 'Mật khẩu đã được đặt lại thành công! Vui lòng đăng nhập.');
        }

        return back()
            ->withErrors(['email' => $this->translateStatus($status)])
            ->withInput($request->only('email'));
    }

    /**
     * Translate a Illuminate\Support\Facades\Password broker status constant
     * into a user-facing Vietnamese message. Pulled out into its own public
     * method so it's unit-testable without a database — Password::reset()
     * itself needs one, this mapping doesn't.
     */
    public function translateStatus(string $status): string
    {
        return match ($status) {
            // Unknown e-mail and bad token get the SAME answer: a distinct "no such account" message here let
            // anyone test which addresses are registered (the forgot-password form is careful about that too).
            Password::INVALID_USER,
            Password::INVALID_TOKEN => 'Link đặt lại mật khẩu không hợp lệ hoặc đã hết hạn. Vui lòng yêu cầu link mới.',
            Password::RESET_THROTTLED => 'Bạn vừa yêu cầu đặt lại mật khẩu, vui lòng thử lại sau ít phút.',
            default => 'Có lỗi xảy ra khi đặt lại mật khẩu. Vui lòng thử lại.',
        };
    }
}
