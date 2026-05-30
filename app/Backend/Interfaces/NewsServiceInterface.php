<?php

namespace App\Backend\Interfaces;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface NewsServiceInterface
{
    public function listNews(array $filters, int $perPage = 20): LengthAwarePaginator;

    /**
     * Crawl all configured RSS sources and persist new items.
     *
     * @return array{synced: int, errors: string[]}
     */
    public function syncFromAllSources(): array;

    /** Source names available for filtering. */
    public function getSources(): array;

    /** All news_categories as Collection<NewsCategory>. */
    public function getCategories(): Collection;
}

