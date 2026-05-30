<?php

namespace App\Console\Commands;

use App\Backend\Interfaces\NewsServiceInterface;
use Illuminate\Console\Command;

class SyncNews extends Command
{
    protected $signature   = 'sync:news';
    protected $description = 'Crawl RSS feeds from all news sources and persist new articles to DB';

    public function __construct(protected NewsServiceInterface $newsService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Syncing news from all RSS sources...');

        $result = $this->newsService->syncFromAllSources();

        $this->info("✓ {$result['synced']} new articles saved.");

        if (!empty($result['errors'])) {
            foreach ($result['errors'] as $error) {
                $this->warn("  ⚠ {$error}");
            }
        }

        return self::SUCCESS;
    }
}
