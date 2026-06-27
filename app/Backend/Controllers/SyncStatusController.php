<?php

namespace App\Backend\Controllers;

use App\Models\CompanyFinancial;
use App\Models\ExchangeRate;
use App\Models\HotIndustry;
use App\Models\News;
use App\Models\StockPrice;
use App\Models\StockSymbol;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SyncStatusController extends Controller
{
    /**
     * Whitelisted commands: key => [artisan_command, args_array]
     * args_array uses the same format as Artisan::call() second argument.
     */
    private const ALLOWED_COMMANDS = [
        'sync:news'              => ['sync:news',              []],
        'sync:exchange-rates'    => ['sync:exchange-rates',    []],
        'sync:hot-industries'    => ['sync:hot-industries',    []],
        'sync:stock-prices'      => ['sync:stock-prices',      []],
        'sync:stock-data'        => ['sync:stock-data',        []],
        'sync:company-financials'=> ['sync:company-financials', ['--stale' => true, '--dispatch' => true]],
    ];

    public function index(): View
    {
        Gate::authorize('manage-features');

        $sources = [
            [
                'key'         => 'sync:news',
                'label'       => 'Tin tức (RSS)',
                'icon'        => 'news',
                'color'       => 'purple',
                'row_count'   => News::count(),
                'last_sync'   => News::max('synced_at'),
                'description' => '5 nguồn RSS: VnExpress, CafeF, Dân Trí — chạy mỗi 30 phút',
            ],
            [
                'key'         => 'sync:exchange-rates',
                'label'       => 'Tỷ giá ngoại tệ',
                'icon'        => 'currency',
                'color'       => 'green',
                'row_count'   => ExchangeRate::count(),
                'last_sync'   => ExchangeRate::max('updated_at'),
                'description' => 'VCB exchange rates — chạy hàng ngày lúc 07:30',
            ],
            [
                'key'         => 'sync:hot-industries',
                'label'       => 'Ngành hot',
                'icon'        => 'flame',
                'color'       => 'orange',
                'row_count'   => HotIndustry::count(),
                'last_sync'   => HotIndustry::max('updated_at'),
                'description' => 'Ngân hàng, BĐS, CNTT — chạy hàng ngày lúc 07:45',
            ],
            [
                'key'         => 'sync:stock-prices',
                'label'       => 'Giá cổ phiếu',
                'icon'        => 'trending-up',
                'color'       => 'blue',
                'row_count'   => StockPrice::count(),
                'last_sync'   => StockPrice::max('date'),
                'description' => 'Giá lịch sử hàng ngày — chạy lúc 15:30 sau khi thị trường đóng cửa',
            ],
            [
                'key'         => 'sync:stock-data',
                'label'       => 'Danh sách cổ phiếu',
                'icon'        => 'database',
                'color'       => 'teal',
                'row_count'   => StockSymbol::count(),
                'last_sync'   => StockSymbol::max('updated_at'),
                'description' => 'Symbol list từ vnstock — chạy mỗi thứ Hai lúc 07:00',
            ],
            [
                'key'         => 'sync:company-financials',
                'label'       => 'Tài chính doanh nghiệp',
                'icon'        => 'report',
                'color'       => 'indigo',
                'row_count'   => CompanyFinancial::count(),
                'last_sync'   => CompanyFinancial::max('synced_at'),
                'description' => 'Income/Balance/Cashflow/Ratio — chạy hàng tháng (ngày 5 lúc 02:00)',
            ],
        ];

        return view('backend.sync-status.index', compact('sources'));
    }

    /**
     * AJAX endpoint: trigger a whitelisted artisan command.
     */
    public function trigger(string $key): JsonResponse
    {
        Gate::authorize('manage-features');

        if (! isset(self::ALLOWED_COMMANDS[$key])) {
            return response()->json(['error' => 'Unknown command.'], 422);
        }

        $command = self::ALLOWED_COMMANDS[$key];
        [$cmd, $args] = $command;

        try {
            Artisan::call($cmd, $args);
            $output   = Artisan::output();

            ActivityLogger::log('admin_action', "Manual sync triggered: {$key}", ['output' => trim(mb_substr($output, 0, 300))]);

            return response()->json([
                'success' => true,
                'message' => trim($output) ?: 'Sync hoàn tất.',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
