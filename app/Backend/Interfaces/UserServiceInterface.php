<?php

namespace App\Backend\Interfaces;

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface UserServiceInterface
{
    public function listUsers(array $filters, int $perPage = 15): LengthAwarePaginator;

    public function getRoles(): Collection;

    public function createUser(array $data): User;

    public function updateUser(User $user, array $data): void;

    public function deleteUser(User $user): void;

    public function findWithRelations(User $user): User;
}
