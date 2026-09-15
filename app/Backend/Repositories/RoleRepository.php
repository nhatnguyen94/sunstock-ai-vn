<?php

namespace App\Backend\Repositories;

use App\Backend\Interfaces\RoleRepositoryInterface;
use App\Models\Role;
use Illuminate\Support\Collection;

class RoleRepository implements RoleRepositoryInterface
{
    public function all(): Collection
    {
        return Role::withCount(['users', 'permissions'])->orderBy('name')->get();
    }

    public function findWithRelations(Role $role): Role
    {
        return $role->load(['permissions', 'users']);
    }

    public function create(array $data): Role
    {
        $role = Role::create([
            'name'         => $data['name'],
            'display_name' => $data['display_name'],
        ]);

        $role->permissions()->sync($data['permissions'] ?? []);

        return $role;
    }

    public function update(Role $role, array $data): void
    {
        $role->update([
            'name'         => $data['name'],
            'display_name' => $data['display_name'],
        ]);

        $role->permissions()->sync($data['permissions'] ?? []);
    }

    public function delete(Role $role): void
    {
        $role->delete();
    }
}
