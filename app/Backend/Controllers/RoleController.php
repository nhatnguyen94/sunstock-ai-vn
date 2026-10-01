<?php

namespace App\Backend\Controllers;

use App\Backend\Interfaces\RoleServiceInterface;
use App\Models\Permission;
use App\Models\Role;
use App\Support\ActivityLogger;
use App\Support\AdminGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function __construct(
        protected RoleServiceInterface $roleService
    ) {}

    public function index(): View
    {
        $roles = $this->roleService->listRoles();

        return view('backend.roles.index', compact('roles'));
    }

    public function create(): View
    {
        $permissions = $this->roleService->getPermissions()->groupBy(fn (Permission $p) => $p->group ?? 'Khác');

        return view('backend.roles.create', compact('permissions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|alpha_dash|unique:roles,name',
            'display_name' => 'required|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ], [
            'name.alpha_dash' => 'Tên vai trò chỉ được chứa chữ, số, gạch ngang và gạch dưới (không dấu, không khoảng trắng).',
            'name.unique' => 'Tên vai trò này đã tồn tại.',
        ]);

        $role = $this->roleService->createRole($validated);

        ActivityLogger::log('admin_action', "Tạo vai trò {$role->name}", ['role_id' => $role->id, 'permission_ids' => $validated['permissions'] ?? []]);

        return redirect()->route('admin.roles.index')
            ->with('success', 'Vai trò đã được tạo thành công!');
    }

    public function edit(Role $role): View
    {
        $role = $this->roleService->findWithRelations($role);
        $permissions = $this->roleService->getPermissions()->groupBy(fn (Permission $p) => $p->group ?? 'Khác');

        return view('backend.roles.edit', compact('role', 'permissions'));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|alpha_dash|unique:roles,name,'.$role->id,
            'display_name' => 'required|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ], [
            'name.alpha_dash' => 'Tên vai trò chỉ được chứa chữ, số, gạch ngang và gạch dưới (không dấu, không khoảng trắng).',
            'name.unique' => 'Tên vai trò này đã tồn tại.',
        ]);

        if ($problem = AdminGuard::roleProblem($role, $validated['name'], $validated['permissions'] ?? [])) {
            return back()->withInput()->with('error', $problem);
        }

        $before = $role->permissions()->pluck('permissions.id')->all();

        $this->roleService->updateRole($role, $validated);

        ActivityLogger::log('admin_action', "Cập nhật vai trò {$role->name}", [
            'role_id' => $role->id,
            'permissions_before' => $before,
            'permissions_after' => array_map('intval', $validated['permissions'] ?? []),
        ]);

        return redirect()->route('admin.roles.index')
            ->with('success', 'Vai trò đã được cập nhật thành công!');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($this->roleService->isSystemRole($role)) {
            return redirect()->route('admin.roles.index')
                ->with('error', 'Không thể xoá vai trò hệ thống ('.$role->display_name.').');
        }

        if ($role->users()->exists()) {
            return redirect()->route('admin.roles.index')
                ->with('error', 'Vai trò đang được gán cho ít nhất một user, không thể xoá.');
        }

        ActivityLogger::log('admin_action', "Xoá vai trò {$role->name}", ['role_id' => $role->id]);

        $this->roleService->deleteRole($role);

        return redirect()->route('admin.roles.index')
            ->with('success', 'Vai trò đã được xoá thành công!');
    }
}
