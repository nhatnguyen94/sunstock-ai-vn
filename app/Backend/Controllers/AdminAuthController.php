<?php
namespace App\Backend\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAuthController extends Controller
{
    public function showLoginForm()
    {
        return view('backend.auth.login');
    }

    // public function login(Request $request)
    // {
    //     $credentials = $request->only('email', 'password');
    //     if (Auth::attempt($credentials)) {
    //         $user = Auth::user();
    //         if ($user->hasRole('admin')) {
    //             return redirect()->route('admin.dashboard');
    //         } else {
    //             Auth::logout();
    //             return redirect()->route('admin.login')->withErrors(['email' => 'Bạn không có quyền truy cập admin!']);
    //         }
    //     }
    //     return back()->withErrors(['email' => 'Thông tin đăng nhập không đúng!']);
    // }

    public function logout()
    {
        Auth::logout();
        return redirect()->route('admin.login');
    }
}