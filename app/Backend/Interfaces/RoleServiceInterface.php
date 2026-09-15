<?php

namespace App\Backend\Interfaces;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Collection;

interface RoleServiceInterface
{
    public function listRoles(): Collection;

    public function findWithRelations(Role $role): Role;

    /** @return Collection<int, Permission> grouped for the picker form */
    public function getPermissions(): Collection;

    public function createRole(array $data): Role;

    public function updateRole(Role $role, array $data): void;

    /** Role hệ thống (admin/webadmin/adminsupport/user) — không cho xoá qua UI. */
    public function isSystemRole(Role $role): bool;

    public function deleteRole(Role $role): void;
}
