<?php

namespace App\Backend\Interfaces;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

interface UserRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator;

    public function findWithRelations(User $user): User;

    /** The list's search and status filters as a query (shared by the screen and the CSV export). */
    public function filtered(array $filters): Builder;

    public function create(array $data): User;

    public function update(User $user, array $data): void;

    public function delete(User $user): void;
}
