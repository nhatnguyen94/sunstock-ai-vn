<?php

namespace App\Frontend\Services;

use App\Frontend\Interfaces\CompanyFinancialRepositoryInterface;
use Illuminate\Support\Facades\Log;

class CompanyFinancialService
{
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
}
