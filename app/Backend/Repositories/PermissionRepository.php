<?php

namespace App\Backend\Repositories;

use App\Backend\Interfaces\PermissionRepositoryInterface;
use App\Models\Permission;
use Illuminate\Support\Collection;

class PermissionRepository implements PermissionRepositoryInterface
{
    public function all(): Collection
    {
        return Permission::withCount('roles')->orderBy('group')->orderBy('name')->get();
    }

    public function findWithRelations(Permission $permission): Permission
    {
        return $permission->load('roles');
    }

    public function create(array $data): Permission
    {
        return Permission::create([
            'name'         => $data['name'],
            'display_name' => $data['display_name'],
            'group'        => $data['group'] ?? null,
        ]);
    }

    public function update(Permission $permission, array $data): void
    {
        $permission->update([
            'name'         => $data['name'],
            'display_name' => $data['display_name'],
            'group'        => $data['group'] ?? null,
        ]);
    }

    public function delete(Permission $permission): void
    {
        $permission->delete();
    }
}
