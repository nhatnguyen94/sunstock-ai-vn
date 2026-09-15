<?php

namespace App\Backend\Services;

use App\Backend\Interfaces\RoleRepositoryInterface;
use App\Backend\Interfaces\RoleServiceInterface;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Collection;

class RoleService implements RoleServiceInterface
{
    /**
     * Role hệ thống — gắn liền với các hasRole(Role::XXX)/Role::XXX hardcode
     * rải rác trong code (vd AdminAuthController, EmailVerificationController,
     * RoleSeeder). Xoá các role này sẽ làm hỏng những chỗ đó nên bị chặn.
     */
    private const SYSTEM_ROLES = [
        Role::ADMIN,
        Role::WEBADMIN,
        Role::ADMIN_SUPPORT,
        Role::USER,
    ];

    public function __construct(
        protected RoleRepositoryInterface $roleRepository
    ) {}

    public function listRoles(): Collection
    {
        return $this->roleRepository->all();
    }

    public function findWithRelations(Role $role): Role
    {
        return $this->roleRepository->findWithRelations($role);
    }

    public function getPermissions(): Collection
    {
        return Permission::orderBy('group')->orderBy('name')->get();
    }

    public function createRole(array $data): Role
    {
        return $this->roleRepository->create($data);
    }

    public function updateRole(Role $role, array $data): void
    {
        $this->roleRepository->update($role, $data);
    }

    public function isSystemRole(Role $role): bool
    {
        return in_array($role->name, self::SYSTEM_ROLES, true);
    }

    public function deleteRole(Role $role): void
    {
        $this->roleRepository->delete($role);
    }
}
