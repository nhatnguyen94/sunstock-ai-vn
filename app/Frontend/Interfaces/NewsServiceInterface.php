<?php

namespace App\Frontend\Interfaces;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface NewsServiceInterface
{
    /** Latest N articles from DB for homepage. */
    public function getLatestNews(int $limit = 8): Collection;

    /** Paginated articles for the /news page (filter by category slug, search). */
    public function getPaginatedNews(array $filters, int $perPage = 15): LengthAwarePaginator;

    /** All categories for nav/filter. */
    public function getCategories(): Collection;
}

