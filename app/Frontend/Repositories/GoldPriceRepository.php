<?php

namespace App\Frontend\Repositories;

use App\Frontend\Interfaces\GoldPriceRepositoryInterface;
use App\Models\GoldPrice;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class GoldPriceRepository implements GoldPriceRepositoryInterface
{
    public function saveQuotes(array $rows): int
    {
        $now = now();
        $inserted = 0;

        foreach ($rows as $row) {
            $row += ['branch' => '', 'sell_price' => null, 'purity' => null, 'world_price' => null, 'metal' => 'gold', 'unit' => 'luong'];
            $key = ['source' => $row['source'], 'product' => $row['product'], 'branch' => $row['branch']];
            // ISO strings ('...Z') never equal the stored DATETIME text, so the dedupe lookup would miss and the unique index would fire
            $row['quoted_at'] = Carbon::parse($row['quoted_at'])->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');

            if ($row['source'] === GoldPrice::SOURCE_SJC) {
                $latest = GoldPrice::where($key)->orderByDesc('quoted_at')->first();
                if ($latest && $latest->buy_price === (int) $row['buy_price'] && $latest->sell_price === ($row['sell_price'] !== null ? (int) $row['sell_price'] : null)) {
                    continue;   // unchanged since we last looked
                }
            }

            $model = GoldPrice::firstOrCreate(
                $key + ['quoted_at' => $row['quoted_at']],
                array_diff_key($row, $key + ['quoted_at' => 1]) + ['synced_at' => $now]
            );

            if ($model->wasRecentlyCreated) {
                $inserted++;
            }
        }

        return $inserted;
    }

    public function latestQuotes(?string $metal = null): Collection
    {
        // Newest row per key: join against the max(quoted_at) per key.
        $newest = GoldPrice::selectRaw('source, product, branch, MAX(quoted_at) as max_at')
            ->when($metal, fn ($q) => $q->where('metal', $metal))
            ->groupBy('source', 'product', 'branch');

        return GoldPrice::joinSub($newest, 'n', function ($join) {
            $join->on('gold_prices.source', '=', 'n.source')
                ->on('gold_prices.product', '=', 'n.product')
                ->on('gold_prices.branch', '=', 'n.branch')
                ->on('gold_prices.quoted_at', '=', 'n.max_at');
        })->select('gold_prices.*')
            ->orderBy('gold_prices.source')
            ->orderByDesc('gold_prices.sell_price')
            ->get();
    }

    public function quoteBefore(string $source, string $product, string $branch, CarbonInterface $before): ?GoldPrice
    {
        return GoldPrice::where(['source' => $source, 'product' => $product, 'branch' => $branch])
            ->where('quoted_at', '<', $before)
            ->orderByDesc('quoted_at')
            ->first();
    }

    public function history(string $source, string $product, string $branch, CarbonInterface $since): Collection
    {
        return GoldPrice::where(['source' => $source, 'product' => $product, 'branch' => $branch])
            ->where('quoted_at', '>=', $since)
            ->orderBy('quoted_at')
            ->get();
    }

    public function exists(string $source, string $product, string $branch): bool
    {
        return GoldPrice::where(['source' => $source, 'product' => $product, 'branch' => $branch])->exists();
    }

    public function latestWorldPrice(): ?array
    {
        $row = GoldPrice::whereNotNull('world_price')->orderByDesc('quoted_at')->first(['world_price', 'quoted_at']);

        return $row ? ['price' => (float) $row->world_price, 'at' => Carbon::parse($row->quoted_at)] : null;
    }

    public function lastSyncedAt(): ?Carbon
    {
        $ts = GoldPrice::max('synced_at');

        return $ts ? Carbon::parse($ts) : null;
    }

    public function count(): int
    {
        return GoldPrice::count();
    }
}
