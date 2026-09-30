<?php

namespace App\Frontend\Interfaces;

use App\Models\Etf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

interface EtfRepositoryInterface
{
    /** @return Collection<int, Etf> ordered by symbol */
    public function all(): Collection;

    public function find(string $symbol): ?Etf;

    public function count(): int;

    public function lastSyncedAt(): ?Carbon;

    /**
     * Insert/update by symbol. Each row: symbol, name, name_en, exchange, kind.
     *
     * @param  array<int, array<string, mixed>> $rows
     * @return int rows written
     */
    public function upsertMany(array $rows): int;

    /** Drop funds that are no longer listed. @param string[] $symbols the symbols to keep */
    public function deleteNotIn(array $symbols): int;

    /**
     * Daily bars per symbol since `$from`, ascending: `[symbol => [[date, close (feed unit), volume], ...]]`.
     *
     * @param  string[] $symbols
     * @return array<string, array<int, array{0:string,1:float,2:int}>>
     */
    public function series(array $symbols, string $from): array;
}
