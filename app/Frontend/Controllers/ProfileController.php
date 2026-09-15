<?php

namespace App\Frontend\Controllers;

use App\Frontend\Interfaces\UserProfileRepositoryInterface;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

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

        // findByUserId() can return null — accounts created via the admin
        // panel don't get a UserProfile row automatically (see UserRepository
        // fix). Rule::unique()->ignore(null) correctly skips the exclusion
        // instead of crashing like the old string-concatenated rule did.
        $profile = $this->profileRepo->findByUserId($user->id);

        $request->validate([
            'username' => [
                'required', 'string', 'max:255',
                Rule::unique('user_profiles', 'username')->ignore($profile?->id),
            ],
            'mobile' => 'nullable|string|max:20',
            'birthday' => 'nullable|date|before_or_equal:today',
            'gender' => 'nullable|in:male,female,other',
            'address' => 'nullable|string|max:255',
            'bio' => 'nullable|string|max:1000',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'current_password' => 'nullable|string',
            'password' => 'nullable|string|min:8|confirmed',
        ], [
            'username.required' => 'Tên người dùng là bắt buộc.',
            'username.unique' => 'Tên người dùng đã tồn tại.',
            'birthday.date' => 'Ngày sinh không hợp lệ.',
            'birthday.before_or_equal' => 'Ngày sinh không được ở tương lai.',
            'gender.in' => 'Giới tính không hợp lệ.',
            'bio.max' => 'Giới thiệu bản thân không được quá 1000 ký tự.',
            'avatar.image' => 'Ảnh đại diện phải là file ảnh.',
            'avatar.mimes' => 'Ảnh đại diện phải có định dạng jpeg, png, jpg, gif hoặc webp.',
            'avatar.max' => 'Ảnh đại diện không được quá 2MB.',
            'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
        ]);

        try {
            // Update profile (creates it first if this account never got one)
            $this->profileRepo->updateOrCreateForUser($user->id, [
                'username' => $request->username,
                'mobile' => $request->mobile,
                'birthday' => $request->birthday,
                'gender' => $request->gender,
                'address' => $request->address,
                'bio' => $request->bio,
                'avatar' => $this->storeAvatar($request, $profile),
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

    /**
     * Resolve the avatar path to save: stores a newly-uploaded file (deleting
     * the old one first), clears it if "remove_avatar" was checked, or keeps
     * the existing value unchanged otherwise. Pulled into its own method so
     * it's unit-testable with Storage::fake() — no request validation or DB
     * needed to exercise it.
     */
    public function storeAvatar(Request $request, ?UserProfile $profile): ?string
    {
        if ($request->hasFile('avatar')) {
            if ($profile?->avatar) {
                Storage::disk('public')->delete($profile->avatar);
            }

            return $request->file('avatar')->store('avatars', 'public');
        }

        if ($request->boolean('remove_avatar')) {
            if ($profile?->avatar) {
                Storage::disk('public')->delete($profile->avatar);
            }

            return null;
        }

        return $profile?->avatar;
    }
}
