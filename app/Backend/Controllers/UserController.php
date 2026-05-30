<?php

namespace App\Backend\Controllers;

use App\Backend\Interfaces\UserServiceInterface;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(
        protected UserServiceInterface $userService
    ) {}

    public function index(Request $request): View
    {
        $users = $this->userService->listUsers($request->only('search'));

        return view('backend.users.index', compact('users'));
    }

    public function create(): View
    {
        $roles = $this->userService->getRoles();

        return view('backend.users.create', compact('roles'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'roles'    => 'required|array|min:1',
            'roles.*'  => 'exists:roles,name',
        ], [
            'roles.required' => 'Phải chọn ít nhất một vai trò.',
            'roles.*.exists' => 'Vai trò không hợp lệ.',
        ]);

        $validated['email_verified_at'] = $request->boolean('email_verified') ? now() : null;

        $this->userService->createUser($validated);

        return redirect()->route('admin.users.index')
            ->with('success', 'User đã được tạo thành công!');
    }

    public function show(User $user): View
    {
        $user = $this->userService->findWithRelations($user);

        return view('backend.users.show', compact('user'));
    }

    public function edit(User $user): View
    {
        $roles = $this->userService->getRoles();
        $user->load('roles');

        return view('backend.users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'roles'    => 'required|array|min:1',
            'roles.*'  => 'exists:roles,name',
        ], [
            'roles.required' => 'Phải chọn ít nhất một vai trò.',
            'roles.*.exists' => 'Vai trò không hợp lệ.',
        ]);

        $validated['email_verified_at'] = $request->status === 'active'
            ? ($user->email_verified_at ?? now())
            : null;

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $this->userService->updateUser($user, $validated);

        return redirect()->route('admin.users.index')
            ->with('success', 'User đã được cập nhật thành công!');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('admin.users.index')
                ->with('error', 'Bạn không thể xóa chính mình!');
        }

        $this->userService->deleteUser($user);

        return redirect()->route('admin.users.index')
            ->with('success', 'User đã được xóa thành công!');
    }
}