<?php

namespace Database\Seeders;

use App\Backend\Services\AdminAlertsService;
use App\Backend\Services\DataQualityService;
use App\Http\Middleware\BlockedIps;
use App\Models\ActivityLog;
use App\Models\AiRequest;
use App\Models\BlockedIp;
use App\Models\LoginAttempt;
use App\Models\News;
use App\Models\PortfolioItem;
use App\Models\SiteSetting;
use App\Models\SyncRun;
use App\Models\User;
use App\Models\WatchlistItem;
use App\Support\SiteSettings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * Removes what DemoAdminFeaturesSeeder created and nothing else: the `member*@demo-admin.sunstock.test` accounts with their AI calls, sign-ins,
 * activity and watchlists, the rows marked "demo", the blocked demo address, the three DEMO… stocks, the pinned / hidden news the seeder chose
 * (remembered in site_settings `demo.seed`) and the prepared notice / AI limits.
 *
 *   php artisan db:seed --class=DemoAdminFeaturesCleanupSeeder
 */
class DemoAdminFeaturesCleanupSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->error('DemoAdminFeaturesCleanupSeeder chỉ chạy ở môi trường local/testing.');

            return;
        }

        $ids = DemoAdminFeaturesSeeder::demoUserIds();

        AiRequest::whereIn('user_id', $ids)->delete();
        LoginAttempt::where('user_agent', 'DemoSeed/1.0')->delete();
        ActivityLog::whereIn('user_id', $ids)->delete();
        WatchlistItem::whereIn('user_id', $ids)->orWhere('symbol', 'DEMOGHOST')->delete();
        PortfolioItem::where('stock_symbol', 'DEMOPHANTOM')->delete();
        SyncRun::where('output', DemoAdminFeaturesSeeder::MARK)->delete();
        BlockedIp::where('ip', DemoAdminFeaturesSeeder::BLOCKED_IP)->delete();
        DemoAdminFeaturesSeeder::purgeStocks();

        $seeded = (array) SiteSettings::get('demo.seed', []);
        if (isset($seeded['news_pinned'])) {
            News::whereKey($seeded['news_pinned'])->update(['pinned_at' => null]);
        }
        if (isset($seeded['news_hidden'])) {
            News::whereKey($seeded['news_hidden'])->update(['is_hidden' => false]);
        }
        SiteSetting::whereIn('key', ['demo.seed', 'announcement', 'ai'])->delete();
        Cache::forget('site-settings:all');

        User::whereIn('id', $ids)->each(fn (User $u) => $u->delete());

        BlockedIps::forget();
        Cache::forget('homepage_news');
        DataQualityService::forget();
        AdminAlertsService::forget();

        $this->command?->info('Đã dọn dữ liệu demo của các tính năng admin.');
    }
}
