<?php

namespace App\Backend\Controllers;

use App\Models\ActivityLog;
use App\Models\CompanyFinancial;
use App\Models\ExchangeRate;
use App\Models\GoldPrice;
use App\Models\HotIndustry;
use App\Models\MarketSnapshot;
use App\Models\News;
use App\Models\Portfolio;
use App\Models\PortfolioTransaction;
use App\Models\Stock;
use App\Models\StockPrice;
use App\Models\User;
use App\Models\WatchlistItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_users' => User::count(),
            'total_portfolios' => Portfolio::count(),
            'total_stocks' => Stock::count(),
            'active_portfolios' => Portfolio::where('is_active', true)->count(),
            'total_news' => News::count(),
            'news_last_sync' => News::max('synced_at'),
            'exchange_rate_rows' => ExchangeRate::count(),
            'exchange_last_sync' => ExchangeRate::max('updated_at'),
            // COUNT(*) over the 5M-row price table is the slowest thing on this page: it is a headline, not a ledger
            'stock_price_rows' => Cache::remember('admin:stock-price-rows', 600, fn () => StockPrice::count()),
            'stock_last_sync' => StockPrice::max('date'),
            'hot_industry_rows' => HotIndustry::count(),
            'financials_rows' => CompanyFinancial::count(),
            'activity_today' => ActivityLog::whereDate('created_at', today())->count(),
            'watchlist_items' => WatchlistItem::count(),
            'transactions' => PortfolioTransaction::count(),
            'unverified_users' => User::where('status', User::STATUS_PENDING)->count(),
            'locked_users' => User::whereIn('status', [User::STATUS_BLOCKED, User::STATUS_INACTIVE])->count(),
            'failed_jobs' => $this->failedJobs(),
        ];

        // Names, e-mails and owners are only loaded for roles that may see them elsewhere in the admin area: the
        // dashboard used to show them to every backend account, bypassing manage-users / manage-features / view-timeline.
        $recent_users = Gate::allows('manage-users') ? User::with('roles')->latest()->take(5)->get() : collect();

        $recent_portfolios = Gate::allows('manage-features')
            ? Portfolio::with('user')->where('is_active', true)->latest()->take(5)->get()
            : collect();

        $recent_activity = Gate::allows('view-timeline') ? ActivityLog::orderByDesc('created_at')->take(8)->get() : collect();

        $sources = $this->sources($stats);

        return view('backend.dashboard.index', compact(
            'stats',
            'recent_users',
            'recent_portfolios',
            'recent_activity',
            'sources'
        ));
    }

    /**
     * One row per data source with a traffic-light status, so "is everything syncing?" is answered at a glance.
     * Thresholds are generous on purpose: a weekend is not an incident.
     *
     * @return array<int, array{label: string, icon: string, rows: int, last: ?Carbon, status: string, note: string}>
     */
    private function sources(array $stats): array
    {
        $market = MarketSnapshot::max('synced_at');
        $gold = GoldPrice::max('synced_at');

        return [
            $this->source('Giá cổ phiếu', 'ti-chart-candle', $stats['stock_price_rows'], $stats['stock_last_sync'] ? Carbon::parse($stats['stock_last_sync'])->endOfDay() : null, 96, 240, 'Phiên gần nhất'),
            $this->source('Tổng quan thị trường', 'ti-activity', MarketSnapshot::count(), $market ? Carbon::parse($market) : null, 30, 96, 'Snapshot 5 phút/lần trong phiên'),
            $this->source('Tỷ giá ngoại tệ', 'ti-currency-dollar', $stats['exchange_rate_rows'], $stats['exchange_last_sync'] ? Carbon::parse($stats['exchange_last_sync']) : null, 48, 120, 'Vietcombank'),
            $this->source('Giá vàng', 'ti-coin', GoldPrice::count(), $gold ? Carbon::parse($gold) : null, 30, 96, 'SJC · BTMC · thế giới'),
            $this->source('Tin tức', 'ti-news', $stats['total_news'], $stats['news_last_sync'] ? Carbon::parse($stats['news_last_sync']) : null, 6, 48, '5 nguồn RSS'),
        ];
    }

    private function source(string $label, string $icon, int $rows, ?Carbon $last, int $okHours, int $warnHours, string $note): array
    {
        $age = $last ? $last->diffInHours(now()) : null;
        $status = match (true) {
            $last === null => 'off',
            $age <= $okHours => 'ok',
            $age <= $warnHours => 'warn',
            default => 'bad',
        };

        return compact('label', 'icon', 'rows', 'last', 'status', 'note');
    }

    private function failedJobs(): int
    {
        try {
            return (int) DB::table('failed_jobs')->count();
        } catch (Throwable) {
            return 0;
        }
    }
}
