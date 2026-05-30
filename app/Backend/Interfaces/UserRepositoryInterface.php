<?php

namespace App\Backend\Interfaces;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator;

    public function findWithRelations(User $user): User;

    public function create(array $data): User;

    public function update(User $user, array $data): void;

    public function delete(User $user): void;
}
