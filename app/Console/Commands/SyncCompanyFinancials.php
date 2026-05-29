<?php

namespace App\Console\Commands;

use App\Frontend\Interfaces\CompanyFinancialRepositoryInterface;
use App\Frontend\Services\CompanyFinancialService;
use App\Jobs\SyncCompanyFinancialJob;
use App\Models\Stock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncCompanyFinancials extends Command
{
    protected $signature = 'sync:company-financials
                            {--symbol= : Sync chi 1 ma cu the (vd: FPT)}
                            {--type=   : income|balance|cashflow|ratio (mac dinh: tat ca)}
                            {--period= : quarter|year (mac dinh: tat ca)}
                            {--stale   : Chi sync record chua co hoac cu hon 30 ngay}
                            {--limit=50 : So ma toi da khi sync tat ca}
                            {--dispatch : Day cong viec vao queue de xu ly song song (nhanh hon)}';

    protected $description = 'Fetch company financial data from vnstock and store in DB. Runs monthly.';

    private const TYPES   = ['income', 'balance', 'cashflow', 'ratio'];
    private const PERIODS = ['quarter', 'year'];

    public function __construct(
        private readonly CompanyFinancialService $financialService,
        private readonly CompanyFinancialRepositoryInterface $repo
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $symbolOpt = $this->option('symbol') ? strtoupper(trim($this->option('symbol'))) : null;
        $typeOpt   = $this->option('type')   ? trim($this->option('type'))   : null;
        $periodOpt = $this->option('period') ? trim($this->option('period')) : null;
        $staleOnly = (bool) $this->option('stale');
        $limit     = (int) $this->option('limit');

        if ($typeOpt && ! in_array($typeOpt, self::TYPES)) {
            $this->error('Invalid --type. Must be one of: ' . implode(', ', self::TYPES));
            return 1;
        }
        if ($periodOpt && ! in_array($periodOpt, self::PERIODS)) {
            $this->error('Invalid --period. Must be one of: ' . implode(', ', self::PERIODS));
            return 1;
        }

        $symbols = $symbolOpt
            ? [$symbolOpt]
            : Stock::orderBy('symbol')->limit($limit)->pluck('symbol')->toArray();

        $types   = $typeOpt   ? [$typeOpt]   : self::TYPES;
        $periods = $periodOpt ? [$periodOpt] : self::PERIODS;

        // ── Queue dispatch mode (fast, parallel) ─────────────────────────────
        if ($this->option('dispatch')) {
            $dispatched = 0;
            $skippedSymbols = 0;

            foreach ($symbols as $symbol) {
                // When --stale: only dispatch if at least one type/period is missing or stale
                // This avoids creating jobs that would immediately skip all work
                if ($staleOnly) {
                    $needsSync = false;
                    foreach ($types as $type) {
                        foreach ($periods as $period) {
                            $existing = $this->repo->find($symbol, $type, $period);
                            if (! $existing || $existing->isStale()) {
                                $needsSync = true;
                                break 2;
                            }
                        }
                    }
                    if (! $needsSync) {
                        $skippedSymbols++;
                        continue;
                    }
                }

                SyncCompanyFinancialJob::dispatch($symbol, $types, $periods, $staleOnly);
                $dispatched++;
            }

            $this->info("Dispatched {$dispatched} jobs | Skipped (all fresh): {$skippedSymbols}");
            if ($dispatched > 0) {
                $this->line("Each job: {$dispatched} symbols × " . count($types) . ' types × ' . count($periods) . " periods. Workers process in parallel.");
            }
            return 0;
        }

        // ── Sequential mode (default, shows live progress) ───────────────────
        $total = $skipped = $failed = $unavailable = 0;

        foreach ($symbols as $symbol) {
            foreach ($types as $type) {
                foreach ($periods as $period) {
                    // --stale: skip fresh records (< STALE_DAYS old)
                    if ($staleOnly) {
                        $existing = $this->repo->find($symbol, $type, $period);
                        if ($existing && ! $existing->isStale()) {
                            $skipped++;
                            continue;
                        }
                    }

                    $result = $this->financialService->syncSymbol($symbol, $type, $period);

                    // Real error (Python crash, invalid JSON, network issue)
                    if (isset($result['error'])) {
                        $this->warn("  ERR  {$symbol}/{$type}/{$period}: {$result['error']}");
                        Log::warning('sync:company-financials error', [
                            'symbol' => $symbol, 'type' => $type, 'period' => $period,
                            'error'  => $result['error'],
                        ]);
                        $failed++;
                        continue;
                    }

                    // Empty data = this stock simply doesn't publish this report type/period
                    // (e.g. ETFs have no income statement; some stocks have no quarterly cashflow)
                    if (empty($result['data'])) {
                        $this->line("  --   {$symbol}/{$type}/{$period}: not available");
                        $unavailable++;
                        continue;
                    }

                    $this->line("  OK   {$symbol}/{$type}/{$period} - " . count($result['data']) . ' rows');
                    $total++;
                }
            }
        }

        $this->info("Done. Synced: {$total} | Skipped (fresh): {$skipped} | Not available: {$unavailable} | Errors: {$failed}");
        Log::info('sync:company-financials complete', compact('total', 'skipped', 'unavailable', 'failed'));

        return $failed > 0 ? 1 : 0;
    }
}
