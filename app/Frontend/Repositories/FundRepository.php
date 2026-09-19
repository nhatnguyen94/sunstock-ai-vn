<?php

namespace App\Frontend\Repositories;

use App\Frontend\Interfaces\FundRepositoryInterface;
use App\Models\Fund;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class FundRepository implements FundRepositoryInterface
{
    /** Columns a caller may sort by (whitelist — never interpolate user input into SQL). */
    public const SORTABLE = [
        'short_name', 'nav', 'management_fee', 'inception_date',
        'nav_change_1m', 'nav_change_3m', 'nav_change_6m', 'nav_change_12m',
        'nav_change_24m', 'nav_change_36m', 'nav_change_36m_annualized', 'nav_change_inception',
    ];

    public function search(array $filters): Collection
    {
        $query = Fund::query();

        if (! empty($filters['type'])) {
            $query->where('type_code', $filters['type']);
        }

        if (! empty($filters['owner'])) {
            $query->where('fund_owner_name', $filters['owner']);
        }

        if (! empty($filters['q'])) {
            $term = '%' . addcslashes($filters['q'], '%_\\') . '%';
            $query->where(function ($q) use ($term) {
                $q->where('short_name', 'like', $term)->orWhere('name', 'like', $term);
            });
        }

        $sort = in_array($filters['sort'] ?? null, self::SORTABLE, true) ? $filters['sort'] : 'nav_change_12m';
        $dir  = ($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        // NULL (fund too young for this window) must never outrank real numbers.
        return $query->orderByRaw("{$sort} IS NULL")
            ->orderBy($sort, $dir)
            ->orderBy('short_name')
            ->get();
    }

    public function findByShortName(string $shortName): ?Fund
    {
        return Fund::where('short_name', $shortName)->first();
    }

    public function findManyByShortNames(array $shortNames): Collection
    {
        $funds = Fund::whereIn('short_name', $shortNames)->get()->keyBy('short_name');

        return new Collection(
            collect($shortNames)->map(fn ($code) => $funds->get($code))->filter()->values()->all()
        );
    }

    public function upsertMany(array $rows): int
    {
        $now = now();
        $n   = 0;

        foreach ($rows as $row) {
            if (empty($row['short_name'])) {
                continue;
            }
            Fund::updateOrCreate(
                ['short_name' => $row['short_name']],
                array_merge($row, ['synced_at' => $now])
            );
            $n++;
        }

        return $n;
    }

    public function count(): int
    {
        return Fund::count();
    }

    public function lastSyncedAt(): ?Carbon
    {
        $ts = Fund::max('synced_at');

        return $ts ? Carbon::parse($ts) : null;
    }

    public function typeCounts(): array
    {
        return Fund::selectRaw('type_code, COUNT(*) as c')
            ->groupBy('type_code')
            ->pluck('c', 'type_code')
            ->map(fn ($c) => (int) $c)
            ->all();
    }

    public function owners(): array
    {
        return Fund::whereNotNull('fund_owner_name')
            ->distinct()
            ->orderBy('fund_owner_name')
            ->pluck('fund_owner_name')
            ->all();
    }

    public function typeStats(): array
    {
        return Fund::selectRaw('type_code, COUNT(*) as c, AVG(nav_change_12m) as avg_12m, AVG(management_fee) as avg_fee')
            ->groupBy('type_code')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->type_code => [
                'count'   => (int) $r->c,
                'avg_12m' => $r->avg_12m !== null ? round((float) $r->avg_12m, 2) : null,
                'avg_fee' => $r->avg_fee !== null ? round((float) $r->avg_fee, 2) : null,
            ]])
            ->all();
    }
}
