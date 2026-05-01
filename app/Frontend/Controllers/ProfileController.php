<?php

namespace App\Frontend\Controllers;

use App\Frontend\Interfaces\UserProfileRepositoryInterface;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    protected $profileRepo;

    public function __construct(UserProfileRepositoryInterface $profileRepo)
    {
        $this->middleware('auth');
        $this->profileRepo = $profileRepo;
    }

    public function show()
    {
        $user = Auth::user();
        $profile = $this->profileRepo->findByUserId($user->id);

        return view('profile.show', compact('user', 'profile'));
    }

    public function edit()
    {
        $user = Auth::user();
        $profile = $this->profileRepo->findByUserId($user->id);

        return view('profile.edit', compact('user', 'profile'));
    }

    public function update(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập để tiếp tục.');
        }

        $profile = $this->profileRepo->findByUserId($user->id);

        $request->validate([
            'username' => 'required|string|max:255|unique:user_profiles,username,'.$profile->id,
            'mobile' => 'nullable|string|max:20',
            'current_password' => 'nullable|string',
            'password' => 'nullable|string|min:8|confirmed',
        ], [
            'username.required' => 'Tên người dùng là bắt buộc.',
            'username.unique' => 'Tên người dùng đã tồn tại.',
            'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
        ]);

        try {
            // Update profile
            $this->profileRepo->update($profile, [
                'username' => $request->username,
                'mobile' => $request->mobile,
            ]);

            // Update user name
            $user->name = $request->username;
            $user->save();

            // Update password if provided
            if ($request->filled('password')) {
                if (! $request->filled('current_password') ||
                    ! Hash::check($request->current_password, $user->password)) {
                    return back()->withErrors(['current_password' => 'Mật khẩu hiện tại không chính xác.']);
                }

                $user->password = Hash::make($request->password);
                $user->save();
            }

            return redirect()->route('profile.show')->with('success', 'Cập nhật profile thành công!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Có lỗi xảy ra khi cập nhật profile.'])
                ->withInput();
        }
    }
}
