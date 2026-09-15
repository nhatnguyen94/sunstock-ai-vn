<?php

namespace App\Backend\Interfaces;

use App\Models\Permission;
use Illuminate\Support\Collection;

interface PermissionRepositoryInterface
{
    public function all(): Collection;

    public function findWithRelations(Permission $permission): Permission;

    public function create(array $data): Permission;

    public function update(Permission $permission, array $data): void;

    public function delete(Permission $permission): void;
}
