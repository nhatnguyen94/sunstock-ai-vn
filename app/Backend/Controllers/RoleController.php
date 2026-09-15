<?php

namespace App\Backend\Controllers;

use App\Backend\Interfaces\RoleServiceInterface;
use App\Models\Permission;
use App\Models\Role;
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
            'name'          => 'required|string|max:255|alpha_dash|unique:roles,name',
            'display_name'  => 'required|string|max:255',
            'permissions'   => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ], [
            'name.alpha_dash' => 'Tên vai trò chỉ được chứa chữ, số, gạch ngang và gạch dưới (không dấu, không khoảng trắng).',
            'name.unique'      => 'Tên vai trò này đã tồn tại.',
        ]);

        $this->roleService->createRole($validated);

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
            'name'          => 'required|string|max:255|alpha_dash|unique:roles,name,' . $role->id,
            'display_name'  => 'required|string|max:255',
            'permissions'   => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ], [
            'name.alpha_dash' => 'Tên vai trò chỉ được chứa chữ, số, gạch ngang và gạch dưới (không dấu, không khoảng trắng).',
            'name.unique'      => 'Tên vai trò này đã tồn tại.',
        ]);

        $this->roleService->updateRole($role, $validated);

        return redirect()->route('admin.roles.index')
            ->with('success', 'Vai trò đã được cập nhật thành công!');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($this->roleService->isSystemRole($role)) {
            return redirect()->route('admin.roles.index')
                ->with('error', 'Không thể xoá vai trò hệ thống (' . $role->display_name . ').');
        }

        if ($role->users()->exists()) {
            return redirect()->route('admin.roles.index')
                ->with('error', 'Vai trò đang được gán cho ít nhất một user, không thể xoá.');
        }

        $this->roleService->deleteRole($role);

        return redirect()->route('admin.roles.index')
            ->with('success', 'Vai trò đã được xoá thành công!');
    }
}
