<?php

namespace App\Backend\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\AuthRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AdminAuthController extends Controller
{
    /**
     * Hiển thị form đăng nhập admin
     */
    public function showLoginForm()
    {
        // Nếu đã đăng nhập và có quyền admin, redirect to dashboard
        if (Auth::check() && Auth::user()->canAccessBackend()) {
            return redirect()->route('admin.dashboard');
        }

        return view('backend.auth.login');
    }

    /**
     * Xử lý đăng nhập admin
     */
    public function login(Request $request)
    {
        $request->merge(['email' => AuthRules::normalizeEmail($request->input('email'))]);

        $request->validate([
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|max:255',
        ], [
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không hợp lệ.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
        ]);

        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember');

        // Only an active account with a confirmed e-mail may enter (a blocked or switched-off administrator is
        // answered like a wrong password: this form must not reveal what state an account is in)
        $mayEnter = fn ($query) => $query->where('status', User::STATUS_ACTIVE)->whereNotNull('email_verified_at');

        // Thử đăng nhập
        if (Auth::attempt($credentials + [$mayEnter], $remember)) {
            $user = Auth::user();

            // A valid account without backend rights is answered exactly like a wrong password, so this form
            // cannot be used to learn that someone's frontend credentials are right.
            if (! $user->canAccessBackend()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            } else {
                $request->session()->regenerate();

                // Đăng nhập thành công
                ActivityLogger::log('admin_login', "Admin đăng nhập: {$user->name}");

                return redirect()->intended(route('admin.dashboard'))
                    ->with('success', "Chào mừng {$user->name}, bạn đã đăng nhập thành công!");
            }
        }

        // Đăng nhập thất bại
        Log::warning('Admin login failed', ['email' => $credentials['email'], 'ip' => $request->ip()]);

        return back()->withErrors([
            'email' => 'Thông tin đăng nhập không chính xác.',
        ])->onlyInput('email');
    }

    /**
     * Đăng xuất admin
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')
            ->with('success', 'Đã đăng xuất khỏi khu vực quản trị thành công.');
    }

    /**
     * Hiển thị danh sách session đang hoạt động (cho admin)
     */
    public function activeSessions()
    {
        // Chỉ Admin mới có quyền xem
        if (! Auth::user()->hasRole(Role::ADMIN)) {
            return redirect()->route('admin.dashboard')
                ->with('error', 'Bạn không có quyền truy cập tính năng này.');
        }

        // Lấy danh sách user đang online (giả lập)
        $onlineUsers = User::with('roles')
            ->where('updated_at', '>=', now()->subMinutes(5))
            ->get();

        return view('backend.auth.sessions', compact('onlineUsers'));
    }
}
