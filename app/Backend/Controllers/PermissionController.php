<?php

namespace App\Backend\Controllers;

use App\Backend\Interfaces\PermissionServiceInterface;
use App\Models\Permission;
use App\Support\ActivityLogger;
use App\Support\AdminGuard;
use App\Support\TransformerResponse;
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
            'name' => 'required|string|max:255|alpha_dash|unique:permissions,name',
            'display_name' => 'required|string|max:255',
            'group' => 'nullable|string|max:255',
        ], [
            'name.alpha_dash' => 'Tên quyền chỉ được chứa chữ, số, gạch ngang và gạch dưới (không dấu, không khoảng trắng) — vd: manage-alerts.',
            'name.unique' => 'Tên quyền này đã tồn tại.',
        ]);

        $permission = $this->permissionService->createPermission($validated);

        ActivityLogger::log('admin_action', "Tạo quyền {$permission->name}", ['permission_id' => $permission->id]);

        return TransformerResponse::redirectSuccess('admin.permissions.index', TransformerResponse::createdMessage('Quyền hạn'));
    }

    public function edit(Permission $permission): View
    {
        $permission = $this->permissionService->findWithRelations($permission);

        return view('backend.permissions.edit', compact('permission'));
    }

    public function update(Request $request, Permission $permission): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|alpha_dash|unique:permissions,name,'.$permission->id,
            'display_name' => 'required|string|max:255',
            'group' => 'nullable|string|max:255',
        ], [
            'name.alpha_dash' => 'Tên quyền chỉ được chứa chữ, số, gạch ngang và gạch dưới (không dấu, không khoảng trắng) — vd: manage-alerts.',
            'name.unique' => 'Tên quyền này đã tồn tại.',
        ]);

        if ($problem = AdminGuard::permissionProblem($permission, $validated['name'])) {
            return TransformerResponse::backWithInput('error', $problem);
        }

        $oldName = $permission->name;

        $this->permissionService->updatePermission($permission, $validated);

        ActivityLogger::log('admin_action', "Cập nhật quyền {$oldName}", ['permission_id' => $permission->id, 'new_name' => $validated['name']]);

        return TransformerResponse::redirectSuccess('admin.permissions.index', TransformerResponse::updatedMessage('Quyền hạn'));
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        if ($this->permissionService->isCorePermission($permission)) {
            return TransformerResponse::redirectError('admin.permissions.index', 'Không thể xoá quyền hạn lõi ('.$permission->display_name.') — hệ thống dùng nó để tự bảo vệ.');
        }

        ActivityLogger::log('admin_action', "Xoá quyền {$permission->name}", ['permission_id' => $permission->id]);

        $this->permissionService->deletePermission($permission);

        return TransformerResponse::redirectSuccess('admin.permissions.index', TransformerResponse::deletedMessage('Quyền hạn'));
    }
}
