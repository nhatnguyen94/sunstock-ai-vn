<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Frontend\Services\StockService;
use Illuminate\Support\Facades\Log;

class ProcessStockPriceSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 300; // 5 minutes

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * @var array
     */
    protected $symbolChunk;

    /**
     * Create a new job instance.
     *
     * @param array $symbolChunk
     */
    public function __construct(array $symbolChunk)
    {
        $this->symbolChunk = $symbolChunk;
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return int
     */
    public function backoff()
    {
        return 60; // Wait 60 seconds before retrying
    }

    /**
     * Execute the job.
     *
     * @param StockService $stockService
     * @return void
     */
    public function handle(StockService $stockService)
    {
        $symbols = array_column($this->symbolChunk, 'symbol');
        Log::info('Processing stock price sync job for symbols: ' . implode(',', $symbols));
        
        try {
            $stockService->processPriceSyncChunk($this->symbolChunk);
            Log::info('Finished job for symbols: ' . implode(',', $symbols));
        } catch (\Throwable $e) {
            Log::error('Job failed for symbols: ' . implode(',', $symbols), [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Re-throw the exception to allow the job to be retried
            throw $e;
        }
    }
}
