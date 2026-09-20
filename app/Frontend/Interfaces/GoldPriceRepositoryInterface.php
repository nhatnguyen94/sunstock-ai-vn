<?php

namespace App\Frontend\Interfaces;

use App\Models\GoldPrice;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;

interface GoldPriceRepositoryInterface
{
    /**
     * Store quotes. SJC has no timestamp of its own, so an SJC row is only written when its price differs from the
     * latest stored one (rows then mean "the price changed at this moment"). Other sources are idempotent on
     * (source, product, branch, quoted_at).
     *
     * @param  array<int, array<string, mixed>> $rows keys = gold_prices columns
     * @return int rows actually inserted
     */
    public function saveQuotes(array $rows): int;

    /**
     * Newest quote per (source, product, branch), optionally for one metal.
     *
     * @return Collection<int, GoldPrice>
     */
    public function latestQuotes(?string $metal = null): Collection;

    /** Newest quote strictly before $before for one product/branch, or null. */
    public function quoteBefore(string $source, string $product, string $branch, CarbonInterface $before): ?GoldPrice;

    /**
     * Quotes since $since for one product/branch, oldest first.
     *
     * @return Collection<int, GoldPrice>
     */
    public function history(string $source, string $product, string $branch, CarbonInterface $since): Collection;

    public function exists(string $source, string $product, string $branch): bool;

    /** Newest world gold price (USD/oz) we have ever stored, with its timestamp. @return array{price: float, at: Carbon}|null */
    public function latestWorldPrice(): ?array;

    public function lastSyncedAt(): ?Carbon;

    public function count(): int;
}
