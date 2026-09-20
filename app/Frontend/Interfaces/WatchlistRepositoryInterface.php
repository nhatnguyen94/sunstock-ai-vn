<?php

namespace App\Frontend\Interfaces;

interface WatchlistRepositoryInterface
{
    /** The user's symbols, most recently added first. @return string[] */
    public function symbols(int $userId): array;

    public function has(int $userId, string $symbol): bool;

    public function count(int $userId): int;

    /** Idempotent: adding a symbol twice keeps one row. @return bool true when a row was created */
    public function add(int $userId, string $symbol): bool;

    /** @return bool true when a row was deleted */
    public function remove(int $userId, string $symbol): bool;
}
