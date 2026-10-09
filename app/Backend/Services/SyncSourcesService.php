<?php

namespace App\Backend\Services;

use App\Frontend\Services\StockSignalService;
use App\Frontend\Services\WorldMarketService;
use App\Models\CompanyFinancial;
use App\Models\CompanyProfile;
use App\Models\Etf;
use App\Models\ExchangeRate;
use App\Models\Fund;
use App\Models\GoldPrice;
use App\Models\HotIndustry;
use App\Models\MarketSnapshot;
use App\Models\News;
use App\Models\StockPrice;
use App\Models\StockSymbol;
use App\Models\SyncRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Every data source the site refreshes, with its schedule-aware state ("ok" / "late" / "off"), its last recorded run and the latest
 * runs. Shared by Admin > Sync Status and the alert bell; the bell passes `$withCounts = false` so it never counts big tables.
 */
class SyncSourcesService
{
    /**
     * @return array{sources: array<int, array<string, mixed>>, recentRuns: Collection, late: int}
     */
    public function build(bool $withCounts = true): array
    {
        $sources = [
            [
                'key' => 'sync:news',
                'max_age_hours' => 6,
                'label' => 'Tin tức (RSS)',
                'icon' => 'news',
                'color' => 'purple',
                'row_count' => $withCounts ? News::count() : 0,
                'last_sync' => News::max('synced_at'),
                'description' => '5 nguồn RSS: VnExpress, CafeF, Dân Trí — chạy mỗi 30 phút',
            ],
            [
                'key' => 'sync:exchange-rates',
                'max_age_hours' => 48,
                'label' => 'Tỷ giá ngoại tệ',
                'icon' => 'currency',
                'color' => 'green',
                'row_count' => $withCounts ? ExchangeRate::count() : 0,
                'last_sync' => ExchangeRate::max('updated_at'),
                'description' => 'VCB exchange rates — chạy hàng ngày lúc 07:30',
            ],
            [
                'key' => 'sync:hot-industries',
                'max_age_hours' => 48,
                'label' => 'Ngành hot',
                'icon' => 'flame',
                'color' => 'orange',
                'row_count' => $withCounts ? HotIndustry::count() : 0,
                'last_sync' => HotIndustry::max('updated_at'),
                'description' => 'Ngân hàng, BĐS, CNTT — chạy hàng ngày lúc 07:45',
            ],
            [
                'key' => 'sync:stock-prices',
                'max_age_hours' => 96,
                'label' => 'Giá cổ phiếu',
                'icon' => 'trending-up',
                'color' => 'blue',
                'row_count' => $withCounts ? StockPrice::count() : 0,
                'last_sync' => StockPrice::max('date'),
                'description' => 'Giá lịch sử hàng ngày — chạy lúc 15:30 sau khi thị trường đóng cửa',
            ],
            [
                'key' => 'sync:stock-data',
                'max_age_hours' => 200,
                'label' => 'Danh sách cổ phiếu',
                'icon' => 'database',
                'color' => 'cyan',
                'row_count' => $withCounts ? StockSymbol::count() : 0,
                'last_sync' => StockSymbol::max('updated_at'),
                'description' => 'Symbol list từ vnstock — chạy mỗi thứ Hai lúc 07:00',
            ],
            [
                'key' => 'sync:company-financials',
                'max_age_hours' => 1000,
                'label' => 'Tài chính doanh nghiệp',
                'icon' => 'report',
                'color' => 'indigo',
                'row_count' => $withCounts ? CompanyFinancial::count() : 0,
                'last_sync' => CompanyFinancial::max('synced_at'),
                'description' => 'Income/Balance/Cashflow/Ratio — chạy hàng tháng (ngày 5 lúc 02:00)',
            ],
            [
                'key' => 'sync:company-profiles',
                'max_age_hours' => 200,
                'label' => 'Hồ sơ công ty',
                'icon' => 'building',
                'color' => 'cyan',
                'row_count' => $withCounts ? CompanyProfile::count() : 0,
                'last_sync' => CompanyProfile::max('synced_at'),
                'description' => 'Cổ đông, ban lãnh đạo, công ty con, sự kiện — cache khi có người xem; làm mới Chủ nhật 03:00 (nút này: làm mới hồ sơ cũ + nạp thêm 50 mã)',
            ],
            [
                'key' => 'sync:market-overview',
                'max_age_hours' => 96,
                'label' => 'Tổng quan thị trường',
                'icon' => 'chart-line',
                'color' => 'blue',
                'row_count' => $withCounts ? MarketSnapshot::count() : 0,
                'last_sync' => MarketSnapshot::max('synced_at'),
                'description' => 'VN-Index/VN30/HNX/UPCoM, độ rộng thị trường, thanh khoản, top tăng/giảm và giá mọi mã (bảng giá KBS, 1 lần gọi ~4s) — mỗi 5 phút trong phiên giao dịch, 1 lần lúc 18:00',
            ],
            [
                'key' => 'sync:gold-prices',
                'max_age_hours' => 30,
                'label' => 'Giá vàng (SJC, BTMC)',
                'icon' => 'currency',
                'color' => 'yellow',
                'row_count' => $withCounts ? GoldPrice::count() : 0,
                'last_sync' => GoldPrice::max('synced_at'),
                'description' => 'Vàng SJC/BTMC, bạc và giá vàng thế giới — chụp snapshot mỗi 15 phút (7h–19h giờ VN) để tự dựng biểu đồ lịch sử',
            ],
            [
                'key' => 'sync:funds',
                'max_age_hours' => 72,
                'label' => 'Quỹ mở (Fmarket)',
                'icon' => 'chart-pie',
                'color' => 'pink',
                'row_count' => $withCounts ? Fund::count() : 0,
                'last_sync' => Fund::max('synced_at'),
                'description' => 'NAV + lợi suất toàn bộ quỹ mở (1 lần gọi) — chạy hàng ngày lúc 18:30',
            ],
            [
                'key' => 'sync:etfs',
                'max_age_hours' => 200,
                'label' => 'Quỹ ETF (KBS)',
                'icon' => 'chart-arrows',
                'color' => 'cyan',
                'row_count' => $withCounts ? Etf::count() : 0,
                'last_sync' => Etf::max('synced_at'),
                'description' => 'Danh sách ETF và quỹ đóng niêm yết (mã + tên) — chạy hàng tuần Chủ nhật 03:30; giá lấy từ sync:stock-prices',
            ],
            $this->cacheSource('sync:world-markets', 'Chỉ số thế giới', 'chart-line', 'blue', WorldMarketService::CACHE_KEY, fn (array $d) => count($d['markets'] ?? []), 'synced_at',
                'S&P 500, Nasdaq, Dow, FTSE, DAX, Nikkei, Hang Seng, Thượng Hải (MSN) cho dải "Thế giới" ở trang chủ — mỗi 30 phút; lưu trong cache, không có bảng riêng', 3),
            $this->cacheSource('signals:build', 'Tín hiệu hôm nay', 'trending-up', 'orange', StockSignalService::CACHE_KEY, fn (array $d) => (int) ($d['universe'] ?? 0), 'built_at',
                'Vượt đỉnh/thủng đáy 52 tuần, khối lượng đột biến, RSI cực trị — tính từ snapshot mới nhất, ngày thường mỗi 30 phút trong phiên + 18:15; lưu trong cache', 96),
        ];

        // Last run of every command (written by SyncRunRecorder) and the latest runs, so a job that runs but FAILS is visible too
        $lastRuns = SyncRun::query()->orderByDesc('ran_at')->orderByDesc('id')->limit(400)->get()->unique('command')->keyBy('command');
        $recentRuns = SyncRun::query()->orderByDesc('ran_at')->orderByDesc('id')->limit(15)->get();

        $sources = array_map(function (array $src) use ($lastRuns) {
            $last = $src['last_sync'] ? Carbon::parse($src['last_sync']) : null;
            $src['state'] = match (true) {
                $last === null => 'off',
                $last->lt(now()->subHours($src['max_age_hours'])) => 'late',
                default => 'ok',
            };
            $src['last_run'] = $lastRuns->get($src['key']);

            return $src;
        }, $sources);

        $late = collect($sources)->whereIn('state', ['late', 'off'])->count();

        return compact('sources', 'recentRuns', 'late');
    }

    /**
     * A source whose data lives in the cache (no table): read the cached payload directly — never through the service, whose
     * reader queues a refresh job as a side effect, which a monitoring page must not do.
     *
     * @param  callable(array): int  $count
     */
    private function cacheSource(string $key, string $label, string $icon, string $color, string $cacheKey, callable $count, string $stampField, string $description, int $maxAgeHours): array
    {
        $data = Cache::get($cacheKey);
        $data = is_array($data) ? $data : [];

        return [
            'key' => $key,
            'max_age_hours' => $maxAgeHours,
            'label' => $label,
            'icon' => $icon,
            'color' => $color,
            'row_count' => $data ? $count($data) : 0,
            'last_sync' => $data[$stampField] ?? null,
            'description' => $description,
        ];
    }
}
