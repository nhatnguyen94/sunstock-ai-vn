<?php

namespace App\Frontend\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Models\UserProfile;
use App\Support\ActivityLogger;
use App\Support\AuthRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect('/');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->merge(['email' => AuthRules::normalizeEmail($request->input('email'))]);

        $credentials = $request->validate([
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|max:255',
        ]);

        // The e-mail check is part of the attempt itself, so an unverified account never gets a session
        // (not even a short-lived one) and no "remember me" cookie is ever issued for it.
        $verified = fn ($query) => $query->whereNotNull('email_verified_at');

        if (Auth::attempt($credentials + [$verified], $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended('/')->with('success', 'Đăng nhập thành công!');
        }

        // Only someone who already knows the right password learns that the account is merely unverified.
        if (Auth::validate($credentials)) {
            return back()->withErrors([
                'email' => 'Bạn cần xác thực email trước khi đăng nhập. Kiểm tra hòm thư để xác thực tài khoản.',
            ])->onlyInput('email');
        }

        Log::warning('Login failed', ['email' => $credentials['email'], 'ip' => $request->ip()]);

        return back()->withErrors([
            'email' => 'Thông tin đăng nhập không chính xác.',
        ])->onlyInput('email');
    }

    public function showRegisterForm()
    {
        if (Auth::check()) {
            return redirect('/');
        }

        return view('auth.register');
    }

    public function register(Request $request)
    {
        $request->merge(['email' => AuthRules::normalizeEmail($request->input('email'))]);

        $request->validate([
            'username' => [...AuthRules::username(), 'unique:user_profiles,username'],
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'string', AuthRules::password(), 'confirmed'],
            'mobile' => AuthRules::mobile(),
        ], AuthRules::messages() + [
            'username.unique' => 'Tên người dùng đã tồn tại.',
            'email.required' => 'Email là bắt buộc.',
            'email.unique' => 'Email đã được sử dụng.',
            'password.required' => 'Mật khẩu là bắt buộc.',
        ]);

        try {
            // All-or-nothing: a failure after User::create used to leave an account without profile or role.
            $user = DB::transaction(function () use ($request) {
                $user = User::create([
                    'name' => $request->username,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    // email_verified_at stays null: the address must be confirmed before the first login
                ]);

                UserProfile::create([
                    'user_id' => $user->id,
                    'username' => $request->username,
                    'mobile' => $request->mobile,
                ]);

                $user->assignRole(Role::USER);

                return $user;
            });

            $user->sendEmailVerificationNotification();

            ActivityLogger::log('user_register', "User đăng ký: {$user->email}", ['email' => $user->email], $user);

            // Not logged in automatically: the e-mail has to be verified first
            return redirect()->route('login')->with('success', 'Đăng ký thành công! Vui lòng kiểm tra email để xác thực tài khoản trước khi đăng nhập.');
        } catch (\Exception $e) {
            report($e);

            return back()->withErrors(['error' => 'Có lỗi xảy ra khi đăng ký. Vui lòng thử lại.'])
                ->withInput($request->except('password', 'password_confirmation'));
        }
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'Đã đăng xuất thành công!');
    }
}
