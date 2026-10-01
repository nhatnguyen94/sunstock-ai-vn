<?php

namespace App\Frontend\Controllers;

use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\AdminGuard;
use App\Support\AuthRules;
use Illuminate\Auth\Events\Verified;
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
    public function verify(Request $request, int $id, string $hash)
    {
        $user = User::findOrFail($id);

        // `signed` already proved the URL came from us; the hash binds it to the address it was issued for, so a
        // link for an old e-mail address stops working once the address changes.
        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(403, 'Link xác thực không hợp lệ.');
        }

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            // pending -> active; an account an admin blocked or switched off stays that way
            if ($user->status === User::STATUS_PENDING) {
                $user->forceFill(['status' => User::STATUS_ACTIVE])->save();
            }

            event(new Verified($user));
        }

        if (Auth::check()) {
            return redirect('/')->with('success', 'Email đã được xác thực thành công! Chào mừng bạn đến với Stock App.');
        }

        return redirect()->route('login')->with('success', 'Email đã được xác thực thành công! Bạn có thể đăng nhập ngay bây giờ.');
    }

    /**
     * Gửi lại email xác thực
     */
    public function resend(Request $request)
    {
        $request->merge(['email' => AuthRules::normalizeEmail($request->input('email'))]);
        $request->validate(['email' => 'required|string|email|max:255']);

        $user = User::where('email', $request->input('email'))->first();

        // Only an account that is really waiting for its e-mail gets a mail: a blocked or switched-off account must not
        // be able to bring itself back this way, and an active one has nothing to verify.
        if ($user && $user->status === User::STATUS_PENDING && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        // The same answer whatever the address is, so this cannot be used to find out who is registered
        return back()->with('success', 'Nếu email này đang chờ xác thực, chúng tôi đã gửi lại link xác thực. Vui lòng kiểm tra hộp thư (cả thư mục spam).');
    }

    /**
     * Admin manually verifies a user's e-mail. A pending account becomes active; blocked or switched-off accounts keep
     * their status. (Route is inside the admin-only group: AdminOnlyForChanges.)
     */
    public function adminVerify(Request $request, $userId)
    {
        $user = User::findOrFail($userId);

        if ($user->hasVerifiedEmail()) {
            return back()->with('info', 'Tài khoản này đã được xác thực trước đó.');
        }

        $user->markEmailAsVerified();
        if ($user->status === User::STATUS_PENDING) {
            $user->forceFill(['status' => User::STATUS_ACTIVE])->save();
        }

        ActivityLogger::log('admin_action', "Xác thực thủ công email {$user->email}", ['target_user_id' => $user->id]);

        return back()->with('success', "Đã xác thực thủ công tài khoản {$user->email} thành công.");
    }

    /**
     * Admin withdraws the e-mail confirmation: an active account goes back to pending (and its sessions end),
     * so the user has to verify again.
     */
    public function adminUnverify(Request $request, $userId)
    {
        $user = User::findOrFail($userId);

        if (! $user->hasVerifiedEmail()) {
            return back()->with('info', 'Tài khoản này chưa được xác thực.');
        }

        $newStatus = $user->status === User::STATUS_ACTIVE ? User::STATUS_PENDING : $user->status;
        if ($problem = AdminGuard::userChangeProblem($request->user(), $user, $user->getRoleNames(), $newStatus)) {
            return back()->with('error', $problem);
        }

        $user->applyStatus($newStatus);
        $user->email_verified_at = null;
        $user->save();

        ActivityLogger::log('admin_action', "Hủy xác thực email {$user->email}", ['target_user_id' => $user->id, 'status' => $user->status]);

        return back()->with('success', "Đã hủy xác thực tài khoản {$user->email}. User cần verify lại email.");
    }
}
