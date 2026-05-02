<?php

namespace App\Backend\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ], [
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không hợp lệ.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
        ]);

        $credentials = $request->only('email', 'password');
        $remember = $request->filled('remember');

        // Thử đăng nhập
        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();
            
            $user = Auth::user();
            
            // Kiểm tra user có quyền truy cập backend không
            if (!$user->canAccessBackend()) {
                Auth::logout();
                return back()->withErrors([
                    'email' => 'Tài khoản này không có quyền truy cập khu vực quản trị.',
                ])->onlyInput('email');
            }

            // Đăng nhập thành công
            return redirect()->intended(route('admin.dashboard'))
                ->with('success', "Chào mừng {$user->name}, bạn đã đăng nhập thành công!");
        }

        // Đăng nhập thất bại
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
        if (!Auth::user()->hasRole(Role::ADMIN)) {
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
