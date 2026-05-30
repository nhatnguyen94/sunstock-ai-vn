<?php

namespace App\Backend\Interfaces;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

interface NewsRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator;

    /**
     * Bulk-insert news items (must include category_id), skipping duplicates by url_hash.
     * Returns number of newly inserted rows.
     */
    public function insertNew(array $items): int;

    /** All categories as [id => name] map. */
    public function getCategories(): Collection;

    /** Distinct source names currently in DB. */
    public function getSources(): array;

    /** Latest synced_at timestamp across all rows, or null if table empty. */
    public function getLatestSyncTime(): ?Carbon;
}

