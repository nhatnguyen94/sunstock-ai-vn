<?php

namespace App\Console\Commands;

use App\Models\LoginAttempt;
use Illuminate\Console\Command;

class PruneLoginAttempts extends Command
{
    protected $signature = 'logins:prune {--days=90 : Keep sign-in attempts this many days}';

    protected $description = 'Delete sign-in attempts (Admin > Bảo mật) older than 90 days, so the table holds a rolling window and no address lives forever.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $deleted = LoginAttempt::query()->where('created_at', '<', now()->subDays($days))->delete();

        $this->info("Deleted {$deleted} sign-in attempts older than {$days} days.");

        return 0;
    }
}
