<?php

namespace App\Backend\Controllers;

use App\Support\ActivityLogger;
use App\Support\AuthRules;
use App\Support\TransformerResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Self-service account settings for the CURRENTLY logged-in backend user —
 * not gated by any manage-* permission, since every backend account (any
 * role) must be able to change its own password. Deliberately separate from
 * AdminAuthController (login/logout only) and from the Frontend
 * ProfileController (full profile editing — avatar, bio, etc. — which admin
 * accounts can still reach at /profile, this just avoids making password
 * change the one thing that forces a detour out of the backend layout).
 */
class AccountController extends Controller
{
    public function edit(): View
    {
        return view('backend.account.edit');
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => 'required|string|max:255',
            'password' => ['required', 'string', AuthRules::password(), 'confirmed'],
        ], AuthRules::messages() + [
            'current_password.required' => 'Vui lòng nhập mật khẩu hiện tại.',
            'password.required' => 'Vui lòng nhập mật khẩu mới.',
        ]);

        $user = Auth::user();

        if (! Hash::check($request->input('current_password'), $user->password)) {
            return back()->withErrors(['current_password' => 'Mật khẩu hiện tại không đúng.']);
        }

        $user->update(['password' => Hash::make($request->input('password'))]);

        // Signs every OTHER device/session out and refreshes this session's stored password hash
        Auth::logoutOtherDevices($request->input('password'));

        ActivityLogger::log('admin_action', "Admin đổi mật khẩu: {$user->name}");

        return TransformerResponse::redirectSuccess('admin.account.edit', 'Đã đổi mật khẩu thành công.');
    }
}
