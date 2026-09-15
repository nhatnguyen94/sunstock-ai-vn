<?php

namespace App\Backend\Interfaces;

use App\Models\Permission;
use Illuminate\Support\Collection;

interface PermissionServiceInterface
{
    public function listPermissions(): Collection;

    public function findWithRelations(Permission $permission): Permission;

    public function createPermission(array $data): Permission;

    public function updatePermission(Permission $permission, array $data): void;

    /** Permission lõi dùng để bảo vệ chính hệ thống phân quyền — không cho xoá qua UI. */
    public function isCorePermission(Permission $permission): bool;

    public function deletePermission(Permission $permission): void;
}
