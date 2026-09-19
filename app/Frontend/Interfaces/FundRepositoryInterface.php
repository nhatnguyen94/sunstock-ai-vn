<?php

namespace App\Frontend\Interfaces;

use App\Models\Fund;
use Illuminate\Database\Eloquent\Collection;

interface FundRepositoryInterface
{
    /**
     * @param array{type?:?string, q?:?string, owner?:?string, sort?:?string, dir?:?string} $filters
     *        Rows with a NULL value in the sort column always go last, whatever the direction.
     */
    public function search(array $filters): Collection;

    public function findByShortName(string $shortName): ?Fund;

    /**
     * @param  string[] $shortNames
     * @return Collection<int, Fund> in the order the codes were requested
     */
    public function findManyByShortNames(array $shortNames): Collection;

    /**
     * Insert/update funds keyed by short_name. Each row uses the funds table's column names.
     *
     * @param  array<int, array<string, mixed>> $rows
     * @return int rows written
     */
    public function upsertMany(array $rows): int;

    public function count(): int;

    public function lastSyncedAt(): ?\Illuminate\Support\Carbon;

    /** @return array<string, int> type_code => number of funds */
    public function typeCounts(): array;

    /** @return string[] distinct management companies, alphabetical */
    public function owners(): array;

    /** Per fund type: fund count, average 12m return and average fee. @return array<string, array{count:int, avg_12m:?float, avg_fee:?float}> */
    public function typeStats(): array;
}
