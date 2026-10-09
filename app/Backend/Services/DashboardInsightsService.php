<?php

namespace App\Backend\Services;

use App\Models\ActivityLog;
use App\Models\PortfolioItem;
use App\Models\User;
use App\Models\WatchlistItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Numbers behind the dashboard charts, all computed from tables the site already fills: sign-ups per day, activity per hour of the
 * day, the symbols people follow and hold the most, and how many accounts did something this week. Cached for five minutes so an
 * admin refreshing the page does not run five aggregate queries each time. Nothing here identifies a person.
 */
class DashboardInsightsService
{
    public const TZ = 'Asia/Ho_Chi_Minh';

    private const TTL = 300;

    /** @var int rows read to bucket the activity (a safety cap, the log can be long) */
    private const ACTIVITY_CAP = 20000;

    /**
     * @return array{signups: array<int, array{date: string, label: string, count: int}>, signups_total: int, active_7d: int,
     *               top_watched: array<int, array{symbol: string, count: int}>, top_held: array<int, array{symbol: string, count: int}>}
     */
    public function insights(): array
    {
        return Cache::remember('admin:dashboard-insights:'.now(self::TZ)->format('Y-m-d-H'), self::TTL, fn () => [
            'signups' => $this->signups(30),
            'signups_total' => User::where('created_at', '>=', now()->subDays(30))->count(),
            'active_7d' => (int) ActivityLog::where('created_at', '>=', now()->subDays(7))->whereNotNull('user_id')->distinct()->count('user_id'),
            'top_watched' => $this->top(WatchlistItem::query(), 'symbol'),
            'top_held' => $this->top(PortfolioItem::query(), 'stock_symbol'),
        ]);
    }

    /**
     * Activity events per hour of the day (Vietnam time) over the last seven days: 24 numbers. Separate because it reads the activity
     * log, which only roles with `view-timeline` may see.
     *
     * @return array<int, int> hour (0–23) => events
     */
    public function activityByHour(): array
    {
        return Cache::remember('admin:dashboard-activity-hours:'.now(self::TZ)->format('Y-m-d-H'), self::TTL, function () {
            $hours = array_fill(0, 24, 0);
            ActivityLog::query()
                ->where('created_at', '>=', now()->subDays(7))
                ->orderByDesc('created_at')
                ->limit(self::ACTIVITY_CAP)
                ->pluck('created_at')
                ->each(function ($at) use (&$hours) {
                    $hours[(int) Carbon::parse($at)->timezone(self::TZ)->format('G')]++;
                });

            return $hours;
        });
    }

    /** @return array<int, array{date: string, label: string, count: int}> one entry per day, oldest first, empty days included */
    private function signups(int $days): array
    {
        $from = now(self::TZ)->subDays($days - 1)->startOfDay();
        $perDay = User::query()
            ->where('created_at', '>=', $from->copy()->utc())
            ->pluck('created_at')
            ->countBy(fn ($at) => Carbon::parse($at)->timezone(self::TZ)->toDateString());

        return collect(range(0, $days - 1))->map(function (int $i) use ($from, $perDay) {
            $day = $from->copy()->addDays($i);

            return ['date' => $day->toDateString(), 'label' => $day->format('d/m'), 'count' => (int) ($perDay[$day->toDateString()] ?? 0)];
        })->all();
    }

    /** @return array<int, array{symbol: string, count: int}> */
    private function top($query, string $column): array
    {
        return $query->select($column.' as symbol', DB::raw('count(*) as total'))
            ->whereNotNull($column)
            ->groupBy($column)
            ->orderByDesc('total')
            ->orderBy($column)
            ->limit(8)
            ->get()
            ->map(fn ($row) => ['symbol' => (string) $row->symbol, 'count' => (int) $row->total])
            ->all();
    }
}
