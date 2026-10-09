<?php

namespace App\Backend\Controllers;

use App\Backend\Interfaces\UserServiceInterface;
use App\Backend\Services\UserBulkService;
use App\Backend\Services\UserInsightsService;
use App\Models\Role;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\AdminGuard;
use App\Support\AuthRules;
use App\Support\TransformerResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $users = $this->userService->listUsers($request->only('search', 'status'));

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

        return TransformerResponse::redirectSuccess('admin.users.index', TransformerResponse::createdMessage('User'));
    }

    public function show(User $user, UserInsightsService $insightsService): View
    {
        $user = $this->userService->findWithRelations($user);
        $insights = $insightsService->forUser($user);

        return view('backend.users.show', compact('user', 'insights'));
    }

    /** Change several accounts at once; each goes through the same AdminGuard rules as a single edit, and the answer lists what was skipped. */
    public function bulk(Request $request, UserBulkService $bulk): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(array_keys(UserBulkService::ACTIONS))],
            'ids' => 'required|array|min:1|max:'.UserBulkService::MAX_IDS,
            'ids.*' => 'integer',
        ], ['ids.required' => 'Hãy chọn ít nhất một user.', 'ids.max' => 'Mỗi lần chọn tối đa '.UserBulkService::MAX_IDS.' user.']);

        $result = $bulk->apply($data['action'], $data['ids'], $request->user());

        ActivityLogger::log('admin_action', 'Thao tác hàng loạt: '.UserBulkService::ACTIONS[$data['action']].' ('.count($result['done']).' user)', [
            'action' => $data['action'], 'done' => $result['done'], 'skipped' => array_column($result['skipped'], 'id'),
        ]);

        $message = 'Đã '.UserBulkService::ACTIONS[$data['action']].' '.count($result['done']).' user.';
        if ($result['skipped']) {
            $message .= ' Bỏ qua '.count($result['skipped']).': '.collect($result['skipped'])->map(fn ($s) => "{$s['email']} ({$s['why']})")->take(3)->implode('; ');
        }

        return back()->with($result['done'] ? 'success' : 'error', $message);
    }

    /** Download the accounts matching the list's filters as CSV. Every e-mail address of the site in one file: admin role only. */
    public function export(Request $request, UserBulkService $bulk): StreamedResponse
    {
        TransformerResponse::abortUnless($request->user()->hasRole(Role::ADMIN), TransformerResponse::HTTP_FORBIDDEN);

        $filters = $request->only('search', 'status');
        ActivityLogger::log('admin_action', 'Xuất danh sách user ra CSV', ['filters' => $filters]);

        return response()->streamDownload(function () use ($bulk, $filters) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");   // BOM: Excel opens it as UTF-8, so Vietnamese names survive
            foreach ($bulk->csvRows($filters) as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, 'users-'.now('Asia/Ho_Chi_Minh')->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
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
            return TransformerResponse::backWithInput('error', $problem, except: ['password', 'password_confirmation']);
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

        return TransformerResponse::redirectSuccess('admin.users.index', TransformerResponse::updatedMessage('User'));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($problem = AdminGuard::deleteProblem($request->user(), $user)) {
            return TransformerResponse::redirectError('admin.users.index', $problem);
        }

        ActivityLogger::log('admin_action', "Xóa user {$user->email}", [
            'target_user_id' => $user->id, 'roles' => $user->getRoleNames(),
        ]);

        $this->userService->deleteUser($user);

        return TransformerResponse::redirectSuccess('admin.users.index', TransformerResponse::deletedMessage('User'));
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
