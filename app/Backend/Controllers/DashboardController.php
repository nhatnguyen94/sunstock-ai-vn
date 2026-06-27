<?php

namespace App\Backend\Controllers;

use App\Models\CompanyFinancial;
use App\Models\ExchangeRate;
use App\Models\HotIndustry;
use App\Models\News;
use App\Models\Portfolio;
use App\Models\Stock;
use App\Models\StockPrice;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_users'        => User::count(),
            'total_portfolios'   => Portfolio::count(),
            'total_stocks'       => Stock::count(),
            'active_portfolios'  => Portfolio::where('is_active', true)->count(),
            'total_news'         => News::count(),
            'news_last_sync'     => News::max('synced_at'),
            'exchange_rate_rows' => ExchangeRate::count(),
            'exchange_last_sync' => ExchangeRate::max('updated_at'),
            'stock_price_rows'   => StockPrice::count(),
            'stock_last_sync'    => StockPrice::max('date'),
            'hot_industry_rows'  => HotIndustry::count(),
            'financials_rows'    => CompanyFinancial::count(),
            'activity_today'     => ActivityLog::whereDate('created_at', today())->count(),
        ];

        $recent_users = User::with('roles')->latest()->take(5)->get();

        $recent_portfolios = Portfolio::with('user')
            ->where('is_active', true)
            ->latest()
            ->take(5)
            ->get();

        $recent_activity = ActivityLog::orderByDesc('created_at')->take(8)->get();

        return view('backend.dashboard.index', compact(
            'stats',
            'recent_users',
            'recent_portfolios',
            'recent_activity'
        ));
    }
}
