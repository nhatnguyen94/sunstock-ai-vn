<?php

namespace Database\Seeders;

use App\Backend\Services\AdminAlertsService;
use App\Backend\Services\DataQualityService;
use App\Http\Middleware\BlockedIps;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Removes what DemoBulkDataSeeder created and nothing else: the `@bulk.sunstock.test` members with their portfolios, trades, watchlists, AI calls,
 * activity and sign-ins, plus the rows marked "bulk" (sync runs, queue jobs, blocked addresses, scanner sign-ins).
 *
 *   php artisan db:seed --class=DemoBulkDataCleanupSeeder
 */
class DemoBulkDataCleanupSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->error('DemoBulkDataCleanupSeeder chỉ chạy ở môi trường local/testing.');

            return;
        }

        self::purge();
        $this->command?->info('Đã dọn dữ liệu mẫu lớn (DemoBulkDataSeeder).');
    }

    /** Shared with the seeder, which starts by removing its own previous rows so a second run gives the same data. */
    public static function purge(): void
    {
        $ids = User::query()->where('email', 'like', '%@'.DemoBulkDataSeeder::DOMAIN)->pluck('id')->all();

        foreach (array_chunk($ids, 400) as $chunk) {
            $portfolios = DB::table('portfolios')->whereIn('user_id', $chunk)->pluck('id')->all();
            foreach (array_chunk($portfolios, 400) as $portfolioChunk) {
                DB::table('portfolio_transactions')->whereIn('portfolio_id', $portfolioChunk)->delete();
                DB::table('portfolio_items')->whereIn('portfolio_id', $portfolioChunk)->delete();
                DB::table('portfolios')->whereIn('id', $portfolioChunk)->delete();
            }
            DB::table('watchlist_items')->whereIn('user_id', $chunk)->delete();
            DB::table('ai_requests')->whereIn('user_id', $chunk)->delete();
            DB::table('activity_logs')->whereIn('user_id', $chunk)->delete();
            DB::table('login_attempts')->whereIn('user_id', $chunk)->delete();
            DB::table('role_user')->whereIn('user_id', $chunk)->delete();
            DB::table('user_profiles')->whereIn('user_id', $chunk)->delete();
            DB::table('users')->whereIn('id', $chunk)->delete();
        }

        DB::table('login_attempts')->where('user_agent', 'like', DemoBulkDataSeeder::UA.'%')->delete();   // scanners and typos with no account
        DB::table('sync_runs')->where('output', DemoBulkDataSeeder::MARK)->delete();
        DB::table('queue_job_logs')->where('job_id', 'like', 'bulk-%')->delete();
        DB::table('blocked_ips')->where('reason', 'like', 'BULK:%')->delete();

        BlockedIps::forget();
        DataQualityService::forget();
        AdminAlertsService::forget();
    }
}
