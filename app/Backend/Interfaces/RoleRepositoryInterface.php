<?php

namespace App\Backend\Interfaces;

use App\Models\Role;
use Illuminate\Support\Collection;

interface RoleRepositoryInterface
{
    public function all(): Collection;

    public function findWithRelations(Role $role): Role;

    public function create(array $data): Role;

    public function update(Role $role, array $data): void;

    public function delete(Role $role): void;
}
