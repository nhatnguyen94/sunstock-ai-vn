<?php

namespace App\Backend\Interfaces;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ActivityLogRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator;

    public function countByType(): array;
}
