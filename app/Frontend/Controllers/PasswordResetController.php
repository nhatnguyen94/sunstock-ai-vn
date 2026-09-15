<?php

namespace App\Frontend\Controllers;

use App\Models\User;
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
        $request->validate([
            'email' => 'required|email',
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
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không hợp lệ.',
            'password.required' => 'Vui lòng nhập mật khẩu mới.',
            'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->password = Hash::make($password);
                $user->setRememberToken(Str::random(60));
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
            Password::INVALID_USER => 'Không tìm thấy tài khoản với email này.',
            Password::INVALID_TOKEN => 'Link đặt lại mật khẩu không hợp lệ hoặc đã hết hạn. Vui lòng yêu cầu link mới.',
            Password::RESET_THROTTLED => 'Bạn vừa yêu cầu đặt lại mật khẩu, vui lòng thử lại sau ít phút.',
            default => 'Có lỗi xảy ra khi đặt lại mật khẩu. Vui lòng thử lại.',
        };
    }
}
