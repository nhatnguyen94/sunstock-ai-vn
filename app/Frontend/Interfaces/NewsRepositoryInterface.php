<?php

namespace App\Frontend\Interfaces;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface NewsRepositoryInterface
{
    /** Latest N articles for homepage display. */
    public function getLatest(int $limit = 8): Collection;

    /** Paginated articles with optional category slug / search filter. */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator;

    /** All NewsCategory models ordered by name. */
    public function getCategories(): Collection;
}
