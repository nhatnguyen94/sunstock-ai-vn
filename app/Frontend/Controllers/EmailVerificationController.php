<?php

namespace App\Frontend\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmailVerificationController extends Controller
{
    /**
     * Hiển thị thông báo cần verify email
     */
    public function notice()
    {
        if (Auth::check() && Auth::user()->hasVerifiedEmail()) {
            return redirect('/');
        }

        return view('auth.verify-email');
    }

    /**
     * Xác thực email từ link trong email
     */
    public function verify(EmailVerificationRequest $request)
    {
        $request->fulfill();

        event(new Verified($request->user()));

        return redirect('/')->with('success', 'Email đã được xác thực thành công! Chào mừng bạn đến với Stock App.');
    }

    /**
     * Gửi lại email xác thực
     */
    public function resend(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect('/');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('message', 'Link xác thực mới đã được gửi đến email của bạn!');
    }

    /**
     * Admin manually verify user
     */
    public function adminVerify(Request $request, $userId)
    {
        // Chỉ admin mới có quyền verify thủ công
        if (!Auth::user() || !Auth::user()->hasRole('admin')) {
            abort(403, 'Không có quyền thực hiện hành động này.');
        }

        $user = User::findOrFail($userId);
        
        if ($user->hasVerifiedEmail()) {
            return back()->with('info', 'Tài khoản này đã được xác thực trước đó.');
        }

        $user->markEmailAsVerified();

        return back()->with('success', "Đã xác thực thủ công tài khoản {$user->email} thành công.");
    }

    /**
     * Admin unverify user (để test hoặc suspend)
     */
    public function adminUnverify(Request $request, $userId)
    {
        // Chỉ admin mới có quyền bỏ verify
        if (!Auth::user() || !Auth::user()->hasRole('admin')) {
            abort(403, 'Không có quyền thực hiện hành động này.');
        }

        $user = User::findOrFail($userId);
        
        if (!$user->hasVerifiedEmail()) {
            return back()->with('info', 'Tài khoản này chưa được xác thực.');
        }

        $user->email_verified_at = null;
        $user->save();

        return back()->with('success', "Đã hủy xác thực tài khoản {$user->email}. User cần verify lại email.");
    }
}