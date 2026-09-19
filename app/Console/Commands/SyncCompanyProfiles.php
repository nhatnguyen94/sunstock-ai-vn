<?php

namespace App\Console\Commands;

use App\Frontend\Interfaces\CompanyProfileRepositoryInterface;
use App\Frontend\Services\CompanyProfileService;
use App\Jobs\SyncCompanyProfileJob;
use App\Models\Stock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncCompanyProfiles extends Command
{
    protected $signature = 'sync:company-profiles
                            {--symbol= : Sync chỉ 1 mã cụ thể (vd: FPT)}
                            {--seed    : Thêm các mã trong bảng stocks chưa có hồ sơ (làm nóng cache)}
                            {--limit=50 : Số mã tối đa mỗi lần chạy}
                            {--dispatch : Đẩy vào queue để xử lý song song}';

    protected $description = 'Refresh cached company profiles (overview, shareholders, officers, subsidiaries, events) from vnstock.';

    public function __construct(
        private readonly CompanyProfileService $service,
        private readonly CompanyProfileRepositoryInterface $repo
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));

        if ($this->option('symbol')) {
            $symbol = CompanyProfileService::normalizeSymbol((string) $this->option('symbol'));
            if ($symbol === null) {
                $this->error('Invalid --symbol (expected 2-10 letters/digits).');

                return 1;
            }
            $targets = [$symbol];
        } else {
            // Profiles users already looked at that have gone stale come first: they are
            // the ones somebody will actually open again.
            $targets = $this->repo->staleSymbols($limit);

            if ($this->option('seed') && count($targets) < $limit) {
                $have = array_flip($this->repo->allSymbols());
                $new  = Stock::orderBy('symbol')->pluck('symbol')
                    ->reject(fn ($s) => isset($have[$s]))
                    ->take($limit - count($targets))
                    ->all();
                $targets = array_merge($targets, $new);
            }
        }

        if ($targets === []) {
            $this->info('Nothing to sync — every cached profile is fresh.');

            return 0;
        }

        if ($this->option('dispatch')) {
            foreach ($targets as $symbol) {
                SyncCompanyProfileJob::dispatch($symbol);
            }
            $this->info('Dispatched ' . count($targets) . ' profile jobs.');

            return 0;
        }

        $ok = $missing = $failed = 0;
        foreach ($targets as $symbol) {
            $result = $this->service->sync($symbol);

            if (isset($result['error'])) {
                if ($result['not_found'] ?? false) {
                    $this->line("  --   {$symbol}: no profile available");
                    $missing++;
                } else {
                    $this->warn("  ERR  {$symbol}: {$result['error']}");
                    $failed++;
                }
                continue;
            }

            $this->line("  OK   {$symbol}");
            $ok++;
        }

        $this->info("Done. Synced: {$ok} | Not available: {$missing} | Errors: {$failed}");
        Log::info('sync:company-profiles complete', compact('ok', 'missing', 'failed'));

        return $failed > 0 ? 1 : 0;
    }
}
