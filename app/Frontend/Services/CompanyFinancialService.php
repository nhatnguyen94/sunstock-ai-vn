<?php

namespace App\Frontend\Services;

use App\Frontend\Interfaces\CompanyFinancialRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CompanyFinancialService
{
    /**
     * Vietnamese ratio label (as returned by vnstock) => short screener key.
     */
    private const SCREENER_METRICS = [
        'Chỉ số giá thị trường trên thu nhập (P/E)' => 'pe',
        'Chỉ số giá thị trường trên giá trị sổ sách (P/B)' => 'pb',
        'Thu nhập trên mỗi cổ phần của 4 quý gần nhất (EPS)' => 'eps',
        'Tỷ suất lợi nhuận trên vốn chủ sở hữu bình quân (ROEA)' => 'roe',
        'Tỷ suất sinh lợi trên tổng tài sản bình quân (ROAA)' => 'roa',
        'Tỷ suất cổ tức' => 'dividend_yield',
        'Tỷ số Nợ trên Vốn chủ sở hữu' => 'debt_equity',
    ];

    public function __construct(
        private readonly CompanyFinancialRepositoryInterface $repo
    ) {}

    /**
     * Get financial data for a symbol+type+period.
     *
     * Priority:
     *   1. Return from DB if cached and non-empty.
     *   2. Fetch live from Python, persist to DB, return result.
     *
     * @return array{data: array, periods: array, source: string, synced_at?: string}|array{error: string}
     */
    public function getFinancialData(string $symbol, string $type, string $period): array
    {
        // ── 1. DB cache hit ──────────────────────────────────────────────────
        $record = $this->repo->find($symbol, $type, $period);

        if ($record && ! empty($record->raw_data['data'])) {
            $data              = $record->raw_data;
            $data['source']    = 'db';
            $data['synced_at'] = $record->synced_at?->toDateString();
            return $data;
        }

        // ── 2. Live fetch from Python ────────────────────────────────────────
        $result = $this->fetchFromPython($symbol, $type, $period);

        if (isset($result['error'])) {
            return $result;
        }

        // Persist so next request is instant
        if (! empty($result['data'])) {
            $this->repo->upsert($symbol, $type, $period, $result);
        }

        $result['source'] = 'live';
        return $result;
    }

    /**
     * Force-fetch from Python and persist to DB (skips cache).
     * Used by the sync:company-financials Artisan command.
     *
     * @return array{data: array, periods: array, synced_at: string}|array{error: string}
     */
    public function syncSymbol(string $symbol, string $type, string $period): array
    {
        $result = $this->fetchFromPython($symbol, $type, $period);

        if (isset($result['error']) || empty($result['data'])) {
            return $result;
        }

        $this->repo->upsert($symbol, $type, $period, $result);

        return $result;
    }

    /**
     * Call py/get_company_finance.py and return decoded JSON.
     *
     * @return array
     */
    private function fetchFromPython(string $symbol, string $type, string $period): array
    {
        $pythonPath = config('services.python.path', 'python');
        $scriptPath = base_path('py/get_company_finance.py');

        $cmd = escapeshellarg($pythonPath)
            . ' ' . escapeshellarg($scriptPath)
            . ' ' . escapeshellarg($symbol)
            . ' ' . escapeshellarg($type)
            . ' ' . escapeshellarg($period);

        $output    = [];
        $returnVar = 0;
        // Redirect stderr to NUL on Windows, /dev/null on Unix
        $redirect = DIRECTORY_SEPARATOR === '\\' ? ' 2>NUL' : ' 2>/dev/null';
        exec($cmd . $redirect, $output, $returnVar);

        // Scan backward from last line to find JSON output
        for ($i = count($output) - 1; $i >= 0; $i--) {
            $line = trim($output[$i]);
            if (str_starts_with($line, '{')) {
                $decoded = json_decode($line, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
            }
        }

        Log::warning('CompanyFinancialService: no JSON from Python', [
            'symbol' => $symbol, 'type' => $type, 'period' => $period,
            'exit'   => $returnVar,
        ]);

        return ['error' => 'Failed to fetch financial data'];
    }

    /**
     * Screen stocks by financial ratios (P/E, P/B, ROE, dividend yield, debt/equity...)
     * using the most recent year column cached in company_financials.
     *
     * @param  array{pe_min?:mixed,pe_max?:mixed,pb_min?:mixed,pb_max?:mixed,roe_min?:mixed,dividend_yield_min?:mixed,debt_equity_max?:mixed,sort?:string,dir?:string}  $filters
     * @return array<int, array<string, mixed>>
     */
    public function screenStocks(array $filters): array
    {
        $rows = Cache::remember('screener_ratio_metrics', 3600, function () {
            return $this->repo->getAllRatiosByPeriod('year')
                ->map(function ($record) {
                    $metrics = $this->extractScreenerMetrics($record->raw_data['data'] ?? []);
                    if (empty($metrics)) {
                        return null;
                    }
                    $metrics['symbol'] = $record->symbol;
                    $metrics['synced_at'] = $record->synced_at?->toDateString();

                    return $metrics;
                })
                ->filter()
                ->values()
                ->all();
        });

        $rows = array_values(array_filter($rows, fn (array $row) => $this->matchesScreenerFilters($row, $filters)));

        $sortKey = in_array($filters['sort'] ?? '', ['pe', 'pb', 'eps', 'roe', 'roa', 'dividend_yield', 'debt_equity'], true)
            ? $filters['sort']
            : 'pe';
        $sortDir = ($filters['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        usort($rows, function (array $a, array $b) use ($sortKey, $sortDir) {
            $av = $a[$sortKey] ?? null;
            $bv = $b[$sortKey] ?? null;
            if ($av === null && $bv === null) return 0;
            if ($av === null) return 1;
            if ($bv === null) return -1;

            return $sortDir === 'desc' ? $bv <=> $av : $av <=> $bv;
        });

        return $rows;
    }

    /**
     * Extract the latest-year value for each metric we care about from a vnstock ratio "data" array.
     */
    private function extractScreenerMetrics(array $dataRows): array
    {
        $metrics = [];

        foreach ($dataRows as $row) {
            $key = self::SCREENER_METRICS[$row['item'] ?? ''] ?? null;
            if (! $key) {
                continue;
            }

            $periodKeys = array_diff(array_keys($row), ['item', 'unit', 'levels']);
            if (empty($periodKeys)) {
                continue;
            }
            sort($periodKeys);
            $latestValue = $row[end($periodKeys)];

            if (is_numeric($latestValue)) {
                $metrics[$key] = (float) $latestValue;
            }
        }

        return $metrics;
    }

    private function matchesScreenerFilters(array $row, array $filters): bool
    {
        $checks = [
            'pe_min' => fn ($v) => isset($row['pe']) && $row['pe'] >= $v,
            'pe_max' => fn ($v) => isset($row['pe']) && $row['pe'] <= $v,
            'pb_min' => fn ($v) => isset($row['pb']) && $row['pb'] >= $v,
            'pb_max' => fn ($v) => isset($row['pb']) && $row['pb'] <= $v,
            'roe_min' => fn ($v) => isset($row['roe']) && $row['roe'] >= $v,
            'dividend_yield_min' => fn ($v) => isset($row['dividend_yield']) && $row['dividend_yield'] >= $v,
            'debt_equity_max' => fn ($v) => isset($row['debt_equity']) && $row['debt_equity'] <= $v,
        ];

        foreach ($checks as $filterKey => $check) {
            $value = $filters[$filterKey] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            if (! is_numeric($value) || ! $check((float) $value)) {
                return false;
            }
        }

        return true;
    }
}
