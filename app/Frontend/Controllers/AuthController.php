<?php

namespace App\Frontend\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Models\UserProfile;
use App\Support\ActivityLogger;
use App\Support\AuthRules;
use App\Support\TransformerResponse;
use Exception;
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

        // The status and e-mail checks are part of the attempt itself, so an account that may not enter never gets a
        // session (not even a short-lived one) and no "remember me" cookie is ever issued for it.
        $mayEnter = fn ($query) => $query->mayEnter();

        if (Auth::attempt($credentials + [$mayEnter], $request->boolean('remember'))) {
            $request->session()->regenerate();

            return TransformerResponse::redirectIntended('/', 'success', 'Đăng nhập thành công!');
        }

        // Only someone who already knows the right password learns why the account may not enter
        // (unverified, switched off or blocked).
        if (Auth::validate($credentials)) {
            $account = Auth::getProvider()->retrieveByCredentials(['email' => $credentials['email']]);

            return back()->withErrors([
                'email' => $account instanceof User ? $account->accessDeniedMessage() : 'Thông tin đăng nhập không chính xác.',
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
                    // email_verified_at stays null and users.status keeps its default (pending): the address must be
                    // confirmed before the first login
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
            return TransformerResponse::redirectSuccess('login', 'Đăng ký thành công! Vui lòng kiểm tra email để xác thực tài khoản trước khi đăng nhập.');
        } catch (Exception $e) {
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

        return TransformerResponse::redirectTo('/', 'success', 'Đã đăng xuất thành công!');
    }
}
