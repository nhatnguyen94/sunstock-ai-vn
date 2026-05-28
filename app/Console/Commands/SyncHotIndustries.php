<?php

namespace App\Console\Commands;

use App\Frontend\Services\StockService;
use App\Models\HotIndustry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncHotIndustries extends Command
{
    protected $signature = 'sync:hot-industries {--limit=100 : Số công ty tối đa mỗi ngành}';
    protected $description = 'Fetch hot industries from Python/vnstock and store in database';

    public function __construct(protected StockService $stockService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Fetching hot industries from Python...');

        try {
            $limit = (int) $this->option('limit');
            $data = $this->stockService->fetchHotIndustriesFromPython($limit);

            if (empty($data)) {
                $this->error('No data returned from Python script.');
                Log::error('sync:hot-industries: empty result from Python');
                return 1;
            }

            // Replace all records atomically
            HotIndustry::truncate();

            $rows = array_map(fn($item) => [
                'symbol'     => $item['symbol'] ?? '',
                'organ_name' => $item['organ_name'] ?? null,
                'icb_name3'  => $item['icb_name3'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ], $data);

            // Insert in chunks to avoid query size limits
            foreach (array_chunk($rows, 200) as $chunk) {
                HotIndustry::insert($chunk);
            }

            // Bust the homepage cache so next request reads fresh data
            Cache::forget('hot_industries');

            $this->info('Synced ' . count($rows) . ' hot industry records.');
            Log::info('sync:hot-industries: synced ' . count($rows) . ' records');

            return 0;
        } catch (\Throwable $e) {
            $this->error('Failed: ' . $e->getMessage());
            Log::error('sync:hot-industries error', ['msg' => $e->getMessage()]);
            return 1;
        }
    }
}
