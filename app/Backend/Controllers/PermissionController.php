<?php

namespace App\Backend\Controllers;

use App\Backend\Interfaces\PermissionServiceInterface;
use App\Models\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PermissionController extends Controller
{
    public function __construct(
        protected PermissionServiceInterface $permissionService
    ) {}

    public function index(): View
    {
        $permissions = $this->permissionService->listPermissions();

        return view('backend.permissions.index', compact('permissions'));
    }

    public function create(): View
    {
        return view('backend.permissions.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255|alpha_dash|unique:permissions,name',
            'display_name' => 'required|string|max:255',
            'group'        => 'nullable|string|max:255',
        ], [
            'name.alpha_dash' => 'Tên quyền chỉ được chứa chữ, số, gạch ngang và gạch dưới (không dấu, không khoảng trắng) — vd: manage-alerts.',
            'name.unique'      => 'Tên quyền này đã tồn tại.',
        ]);

        $this->permissionService->createPermission($validated);

        return redirect()->route('admin.permissions.index')
            ->with('success', 'Quyền hạn đã được tạo thành công!');
    }

    public function edit(Permission $permission): View
    {
        $permission = $this->permissionService->findWithRelations($permission);

        return view('backend.permissions.edit', compact('permission'));
    }

    public function update(Request $request, Permission $permission): RedirectResponse
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255|alpha_dash|unique:permissions,name,' . $permission->id,
            'display_name' => 'required|string|max:255',
            'group'        => 'nullable|string|max:255',
        ], [
            'name.alpha_dash' => 'Tên quyền chỉ được chứa chữ, số, gạch ngang và gạch dưới (không dấu, không khoảng trắng) — vd: manage-alerts.',
            'name.unique'      => 'Tên quyền này đã tồn tại.',
        ]);

        $this->permissionService->updatePermission($permission, $validated);

        return redirect()->route('admin.permissions.index')
            ->with('success', 'Quyền hạn đã được cập nhật thành công!');
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        if ($this->permissionService->isCorePermission($permission)) {
            return redirect()->route('admin.permissions.index')
                ->with('error', 'Không thể xoá quyền hạn lõi (' . $permission->display_name . ') — hệ thống dùng nó để tự bảo vệ.');
        }

        $this->permissionService->deletePermission($permission);

        return redirect()->route('admin.permissions.index')
            ->with('success', 'Quyền hạn đã được xoá thành công!');
    }
}
