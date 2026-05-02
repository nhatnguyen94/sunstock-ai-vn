<?php

namespace App\Backend\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Hiển thị danh sách users
     * Chỉ Admin mới có quyền
     */
    public function index(Request $request)
    {
        // Kiểm tra quyền
        if (!Gate::allows('manage-users')) {
            return redirect()->route('admin.dashboard')->with('error', 'Bạn không có quyền truy cập tính năng này.');
        }

        $users = User::with('roles', 'profile')
            ->when($request->search, function($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->paginate(15);

        return view('backend.users.index', compact('users'));
    }

    /**
     * Hiển thị form tạo user mới
     */
    public function create()
    {
        if (!Gate::allows('manage-users')) {
            return redirect()->route('admin.dashboard')->with('error', 'Bạn không có quyền truy cập tính năng này.');
        }

        $roles = Role::all();
        return view('backend.users.create', compact('roles'));
    }

    /**
     * Lưu user mới
     */
    public function store(Request $request)
    {
        if (!Gate::allows('manage-users')) {
            return redirect()->route('admin.dashboard')->with('error', 'Bạn không có quyền truy cập tính năng này.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'roles' => 'required|array|min:1',
            'roles.*' => 'exists:roles,name',
        ], [
            'roles.required' => 'Phải chọn ít nhất một vai trò.',
            'roles.*.exists' => 'Vai trò không hợp lệ.',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'email_verified_at' => now(),
        ]);

        // Gán multiple roles
        $user->syncRoles($request->roles);

        return redirect()->route('admin.users.index')
            ->with('success', 'User đã được tạo thành công!');
    }

    /**
     * Hiển thị chi tiết user
     */
    public function show(User $user)
    {
        if (!Gate::allows('manage-users')) {
            return redirect()->route('admin.dashboard')->with('error', 'Bạn không có quyền truy cập tính năng này.');
        }

        $user->load('roles', 'profile', 'portfolios');
        return view('backend.users.show', compact('user'));
    }

    /**
     * Hiển thị form chỉnh sửa user
     */
    public function edit(User $user)
    {
        if (!Gate::allows('manage-users')) {
            return redirect()->route('admin.dashboard')->with('error', 'Bạn không có quyền truy cập tính năng này.');
        }

        $roles = Role::all();
        $user->load('roles');
        
        return view('backend.users.edit', compact('user', 'roles'));
    }

    /**
     * Cập nhật user
     */
    public function update(Request $request, User $user)
    {
        if (!Gate::allows('manage-users')) {
            return redirect()->route('admin.dashboard')->with('error', 'Bạn không có quyền truy cập tính năng này.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'roles' => 'required|array|min:1',
            'roles.*' => 'exists:roles,name',
        ], [
            'roles.required' => 'Phải chọn ít nhất một vai trò.',
            'roles.*.exists' => 'Vai trò không hợp lệ.',
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password ? Hash::make($request->password) : $user->password,
        ]);

        // Cập nhật multiple roles
        $user->syncRoles($request->roles);

        return redirect()->route('admin.users.index')
            ->with('success', 'User đã được cập nhật thành công!');
    }

    /**
     * Xóa user
     */
    public function destroy(User $user)
    {
        if (!Gate::allows('manage-users')) {
            return redirect()->route('admin.dashboard')->with('error', 'Bạn không có quyền truy cập tính năng này.');
        }

        // Không cho phép tự xóa chính mình
        if ($user->id === auth()->id()) {
            return redirect()->route('admin.users.index')
                ->with('error', 'Bạn không thể xóa chính mình!');
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'User đã được xóa thành công!');
    }
}