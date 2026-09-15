<?php

namespace App\Backend\Services;

use App\Backend\Interfaces\PermissionRepositoryInterface;
use App\Backend\Interfaces\PermissionServiceInterface;
use App\Models\Permission;
use Illuminate\Support\Collection;

class PermissionService implements PermissionServiceInterface
{
    /**
     * Permission lõi mà chính hệ thống phân quyền dùng để tự bảo vệ — nếu
     * cho xoá, admin có thể tự khoá mình khỏi Admin > Vai trò/Quyền hạn và
     * không còn cách nào cấp lại quyền ngoài việc sửa DB trực tiếp.
     */
    private const CORE_PERMISSIONS = [
        'manage-roles',
        'manage-permissions',
    ];

    public function __construct(
        protected PermissionRepositoryInterface $permissionRepository
    ) {}

    public function listPermissions(): Collection
    {
        return $this->permissionRepository->all();
    }

    public function findWithRelations(Permission $permission): Permission
    {
        return $this->permissionRepository->findWithRelations($permission);
    }

    public function createPermission(array $data): Permission
    {
        return $this->permissionRepository->create($data);
    }

    public function updatePermission(Permission $permission, array $data): void
    {
        $this->permissionRepository->update($permission, $data);
    }

    public function isCorePermission(Permission $permission): bool
    {
        return in_array($permission->name, self::CORE_PERMISSIONS, true);
    }

    public function deletePermission(Permission $permission): void
    {
        $this->permissionRepository->delete($permission);
    }
}
