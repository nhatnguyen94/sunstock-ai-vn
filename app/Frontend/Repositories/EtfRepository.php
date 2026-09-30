<?php

namespace App\Frontend\Repositories;

use App\Frontend\Interfaces\EtfRepositoryInterface;
use App\Models\Etf;
use App\Models\StockPrice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class EtfRepository implements EtfRepositoryInterface
{
    public function all(): Collection
    {
        return Etf::orderBy('symbol')->get();
    }

    public function find(string $symbol): ?Etf
    {
        return Etf::where('symbol', $symbol)->first();
    }

    public function count(): int
    {
        return Etf::count();
    }

    public function lastSyncedAt(): ?Carbon
    {
        $at = Etf::max('synced_at');

        return $at ? Carbon::parse($at) : null;
    }

    public function upsertMany(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }
        $now = now();
        $rows = array_map(fn (array $r) => $r + ['synced_at' => $now, 'created_at' => $now, 'updated_at' => $now], $rows);

        Etf::upsert($rows, ['symbol'], ['name', 'name_en', 'exchange', 'kind', 'synced_at', 'updated_at']);

        return count($rows);
    }

    public function deleteNotIn(array $symbols): int
    {
        return Etf::whereNotIn('symbol', $symbols)->delete();
    }

    public function series(array $symbols, string $from): array
    {
        if ($symbols === []) {
            return [];
        }

        $out = [];
        StockPrice::join('stocks', 'stocks.id', '=', 'stock_prices.stock_id')
            ->whereIn('stocks.symbol', $symbols)
            ->where('stock_prices.date', '>=', $from)
            ->whereNotNull('stock_prices.close')
            ->orderBy('stock_prices.date')
            ->get(['stocks.symbol', 'stock_prices.date', 'stock_prices.close', 'stock_prices.volume'])
            ->each(function ($row) use (&$out) {
                $out[$row->symbol][] = [Carbon::parse($row->date)->toDateString(), (float) $row->close, (int) $row->volume];
            });

        return $out;
    }
}
