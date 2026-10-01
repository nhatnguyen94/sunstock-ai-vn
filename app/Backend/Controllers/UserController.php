<?php

namespace App\Backend\Controllers;

use App\Backend\Interfaces\UserServiceInterface;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\AdminGuard;
use App\Support\AuthRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Writes are admin-only (AdminOnlyForChanges middleware on the route group); AdminGuard keeps the admin role from
 * being locked out by a careless change.
 */
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
        $request->merge(['email' => AuthRules::normalizeEmail($request->input('email'))]);

        $validated = $request->validate([
            'name' => AuthRules::displayName(),
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'string', AuthRules::password(), 'confirmed'],
            'status' => ['required', 'integer', Rule::in(array_keys(User::statusLabels()))],
            'roles' => 'required|array|min:1',
            'roles.*' => 'exists:roles,name',
        ], $this->messages());

        $user = $this->userService->createUser($validated);

        ActivityLogger::log('admin_action', "Tạo user {$user->email} ({$user->statusLabel()})", [
            'target_user_id' => $user->id, 'roles' => $validated['roles'], 'status' => $user->status,
        ]);

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
        $request->merge(['email' => AuthRules::normalizeEmail($request->input('email'))]);

        $validated = $request->validate([
            'name' => AuthRules::displayName(),
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', AuthRules::password(), 'confirmed'],
            'status' => ['required', 'integer', Rule::in(array_keys(User::statusLabels()))],
            'roles' => 'required|array|min:1',
            'roles.*' => 'exists:roles,name',
        ], $this->messages());

        if ($problem = AdminGuard::userChangeProblem($request->user(), $user, $validated['roles'], (int) $validated['status'])) {
            return back()->withInput($request->except('password', 'password_confirmation'))->with('error', $problem);
        }

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $before = ['roles' => $user->getRoleNames(), 'status' => $user->status];

        $this->userService->updateUser($user, $validated);

        ActivityLogger::log('admin_action', "Cập nhật user {$user->email}", [
            'target_user_id' => $user->id,
            'before' => $before,
            'after' => ['roles' => $validated['roles'], 'status' => (int) $validated['status']],
            'password_changed' => isset($validated['password']),
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', 'User đã được cập nhật thành công!');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($problem = AdminGuard::deleteProblem($request->user(), $user)) {
            return redirect()->route('admin.users.index')->with('error', $problem);
        }

        ActivityLogger::log('admin_action', "Xóa user {$user->email}", [
            'target_user_id' => $user->id, 'roles' => $user->getRoleNames(),
        ]);

        $this->userService->deleteUser($user);

        return redirect()->route('admin.users.index')
            ->with('success', 'User đã được xóa thành công!');
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return AuthRules::messages() + [
            'name.regex' => 'Tên chỉ được gồm chữ, số, khoảng trắng và các ký tự . _ -',
            'roles.required' => 'Phải chọn ít nhất một vai trò.',
            'roles.*.exists' => 'Vai trò không hợp lệ.',
            'status.in' => 'Trạng thái không hợp lệ.',
        ];
    }
}
