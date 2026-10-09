<?php

namespace Database\Seeders;

use App\Backend\Services\AdminAlertsService;
use App\Backend\Services\DashboardInsightsService;
use App\Backend\Services\DataQualityService;
use App\Backend\Services\SystemHealthService;
use App\Http\Middleware\BlockedIps;
use App\Models\ActivityLog;
use App\Models\AiRequest;
use App\Models\BlockedIp;
use App\Models\LoginAttempt;
use App\Models\News;
use App\Models\Portfolio;
use App\Models\PortfolioItem;
use App\Models\Role;
use App\Models\Stock;
use App\Models\StockPrice;
use App\Models\SyncRun;
use App\Models\User;
use App\Models\WatchlistItem;
use App\Support\SiteSettings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Data for trying every admin feature added on 9 Oct 2026 with real clicks:
 *
 *   php artisan db:seed --class=DemoAdminFeaturesSeeder      (undo: --class=DemoAdminFeaturesCleanupSeeder)
 *
 * Sync history and a failed run · scheduler heartbeat · AI calls (ok / error / refused, three models, a blocked account, an account at its
 * daily quota) · sign-in history with a password-guessing address and a blocked one · 24 accounts spread over 30 days and a week of
 * activity for the dashboard charts · a pinned and a hidden news article · a prepared (switched OFF) site notice · data-quality problems
 * (a stock with no price, a stale one, an impossible price row, symbols nobody knows).
 *
 * LOCAL ONLY (like DemoUsersSeeder). Idempotent: running it twice leaves the same data. Everything it creates is tied to the
 * `demo-admin.sunstock.test` addresses or marked "demo", so the cleanup seeder can remove it again. NOTE: the three fake DEMO… stocks are also
 * picked up by the daily `sync:stock-prices` (it walks the whole `stocks` table) — run the cleanup when you are done.
 */
class DemoAdminFeaturesSeeder extends Seeder
{
    public const DOMAIN = 'demo-admin.sunstock.test';

    public const BLOCKED_IP = '198.51.100.77';

    public const MARK = 'demo-seed';

    private const MODELS = ['openai/gpt-oss-120b', 'openai/gpt-oss-120b', 'openai/gpt-oss-120b', 'openai/gpt-oss-20b', 'groq/compound-mini'];

    private const QUESTIONS = [
        'VN-Index hôm nay có gì đáng chú ý?', 'Nên phân bổ danh mục thế nào khi lãi suất giảm?', 'FPT có đáng nắm giữ dài hạn không?',
        'So sánh VCB và TCB về hiệu quả sinh lời', 'RSI quá bán nghĩa là gì?', 'Khối ngoại bán ròng ảnh hưởng thế nào đến thị trường?',
        'Cổ phiếu ngân hàng còn hấp dẫn không?', 'Cách đọc bản đồ nhiệt của thị trường', 'Thanh khoản giảm 10% là tín hiệu gì?',
        'ETF VN30 phù hợp với ai?', 'Khi nào nên cắt lỗ?', 'Dự báo ngành bất động sản quý tới',
    ];

    private const WATCH = ['FPT', 'VCB', 'HPG', 'VNM', 'MWG', 'ACB', 'TCB', 'SSI', 'VIC', 'MBB'];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->error('DemoAdminFeaturesSeeder chỉ chạy ở môi trường local/testing.');

            return;
        }

        $this->call([RoleSeeder::class, PermissionSeeder::class, DemoStaffSeeder::class, DemoUsersSeeder::class]);

        $this->demoAdmin();
        $people = $this->people();
        $this->syncRuns();
        Cache::put(SystemHealthService::HEARTBEAT_KEY, now()->toIso8601String(), 3600);
        $this->ai($people);
        $this->logins($people);
        $this->activity($people);
        $this->watchlists($people);
        $this->site();
        $this->news();
        $this->dataQuality($people);

        DataQualityService::forget();
        AdminAlertsService::forget();
        Cache::forget('admin:dashboard-insights:'.now(DashboardInsightsService::TZ)->format('Y-m-d-H'));
        Cache::forget('admin:dashboard-activity-hours:'.now(DashboardInsightsService::TZ)->format('Y-m-d-H'));

        $this->summary();
    }

    // ── accounts ────────────────────────────────────────────────────────────

    /** DemoStaffSeeder makes webadmin and support but no admin: add one (same shared demo password) so every screen can be tried. */
    private function demoAdmin(): void
    {
        $admin = User::firstOrCreate(['email' => 'admin@sunstock.test'], ['name' => 'Demo Admin', 'password' => bcrypt(DemoUsersSeeder::PASSWORD)]);
        $admin->forceFill(['email_verified_at' => $admin->email_verified_at ?? now()]);
        $admin->status = User::STATUS_ACTIVE;
        $admin->save();
        $admin->assignRole(Role::ADMIN);
    }

    /** @return array<int, User> 24 members created over the last 30 days (a few still waiting for their e-mail link) */
    private function people(): array
    {
        $people = [];
        foreach (range(1, 24) as $i) {
            $user = User::firstOrCreate(['email' => "member{$i}@".self::DOMAIN], ['name' => "Thành viên {$i}", 'password' => bcrypt(DemoUsersSeeder::PASSWORD)]);
            $pending = $i % 8 === 0;
            $user->forceFill([
                'created_at' => now()->subDays(($i * 11) % 30)->subHours($i % 9),
                'email_verified_at' => $pending ? null : now()->subDays(($i * 11) % 30),
            ]);
            $user->status = $pending ? User::STATUS_PENDING : User::STATUS_ACTIVE;
            $user->save();
            $user->assignRole(Role::USER);
            $people[$i] = $user;
        }

        return $people;
    }

    // ── sync history ────────────────────────────────────────────────────────

    private function syncRuns(): void
    {
        SyncRun::where('output', self::MARK)->delete();

        // command => [runs, minutes between runs, does the newest run fail?]
        $plan = [
            'sync:news' => [12, 30, false], 'sync:market-overview' => [12, 5, false], 'sync:world-markets' => [8, 30, false],
            'signals:build' => [6, 30, false], 'sync:gold-prices' => [6, 15, true], 'sync:exchange-rates' => [2, 1440, false],
            'sync:stock-prices' => [2, 1440, false], 'sync:funds' => [1, 1440, true],
        ];
        $rows = [];
        foreach ($plan as $command => [$runs, $gap, $failsNow]) {
            foreach (range(0, $runs - 1) as $n) {
                $rows[] = [
                    'command' => $command,
                    'ok' => ! ($failsNow && $n === 0),
                    'duration_ms' => 300 + (crc32($command.$n) % 4200),
                    'output' => self::MARK,
                    'ran_at' => now()->subMinutes(2 + $n * $gap),
                ];
            }
        }
        SyncRun::insert($rows);
    }

    // ── AI ──────────────────────────────────────────────────────────────────

    /** @param array<int, User> $people */
    private function ai(array $people): void
    {
        $ids = collect($people)->pluck('id');
        AiRequest::whereIn('user_id', $ids)->delete();
        SiteSettings::set('ai', ['enabled' => true, 'daily_limit' => 20]);

        $rows = [];
        $make = function (User $user, int $minutesAgo, int $seed, ?string $forceStatus = null) use (&$rows) {
            $roll = $seed % 100;
            $status = $forceStatus ?? ($roll < 76 ? 'ok' : ($roll < 88 ? 'error' : 'refused'));
            $kind = $seed % 6 === 0 ? 'predict' : 'chat';
            $rows[] = [
                'user_id' => $user->id, 'kind' => $kind, 'status' => $status,
                'model' => $status === 'ok' ? self::MODELS[$seed % count(self::MODELS)] : null,
                'duration_ms' => $status === 'refused' ? 3 : 900 + ($seed * 137 % 5200),
                'question' => $kind === 'chat' ? self::QUESTIONS[$seed % count(self::QUESTIONS)] : null,
                'created_at' => now()->subMinutes($minutesAgo),
            ];
        };

        // a week of use by eight accounts
        foreach (range(1, 70) as $n) {
            $make($people[1 + ($n * 5) % 8], 20 + $n * 140, $n * 7 + 3);
        }
        // one account that already used its 20 calls today (the next one is refused) …
        foreach (range(1, 20) as $n) {
            $make($people[2], 5 + $n * 6, $n, 'ok');
        }
        $make($people[2], 2, 1, 'refused');
        // … and a burst of errors in the last day, so the "AI lỗi nhiều" alert has something to say
        foreach (range(1, 12) as $n) {
            $make($people[3], 10 + $n * 40, $n, 'error');
        }
        AiRequest::insert($rows);

        $people[8]->forceFill(['ai_blocked_at' => now()->subDay()])->save();   // blocked from the AI by an admin
    }

    // ── sign-ins and blocking ───────────────────────────────────────────────

    /** @param array<int, User> $people */
    private function logins(array $people): void
    {
        LoginAttempt::where('user_agent', 'DemoSeed/1.0')->delete();
        $rows = [];
        $row = fn (?User $u, ?string $email, string $ip, bool $ok, string $door, $at) => [
            'user_id' => $u?->id, 'email' => $email, 'ip' => $ip, 'user_agent' => 'DemoSeed/1.0', 'success' => $ok, 'door' => $door, 'created_at' => $at,
        ];

        foreach ($people as $i => $user) {
            $rows[] = $row($user, $user->email, '203.0.113.'.(10 + $i), true, 'web', now()->subHours(1 + $i));
        }
        foreach (['admin' => 'admin@sunstock.test', 'support' => 'support@sunstock.test', 'webadmin' => 'webadmin@sunstock.test'] as $door => $email) {
            $rows[] = $row(User::firstWhere('email', $email), $email, '203.0.113.9', true, 'admin', now()->subMinutes(30));
        }
        // a stranger guessing passwords from one address, trying many e-mails (suspicious: >= 10 failures in the last hour) …
        foreach (['admin', 'root', 'test', 'info', 'support', 'user', 'demo', 'ceo', 'boss', 'sale', 'hr', 'it', 'manager', 'owner'] as $n => $guess) {
            $rows[] = $row(null, "{$guess}@example.com", '198.51.100.41', false, 'web', now()->subMinutes(3 + $n * 2));
        }
        // … a clumsy member (not suspicious), and someone at the admin door
        foreach (range(1, 3) as $n) {
            $rows[] = $row($people[4], $people[4]->email, '198.51.100.52', false, 'web', now()->subMinutes(20 + $n));
        }
        foreach (range(1, 4) as $n) {
            $rows[] = $row(null, 'admin@sunstock.test', '198.51.100.60', false, 'admin', now()->subMinutes(40 + $n));
        }
        LoginAttempt::insert($rows);

        BlockedIp::updateOrCreate(['ip' => self::BLOCKED_IP], ['reason' => 'DEMO: dò mật khẩu từ tuần trước', 'blocked_by' => User::firstWhere('email', 'admin@sunstock.test')?->id, 'created_at' => now()->subDays(2)]);
        BlockedIps::forget();
    }

    // ── a week of activity for the hourly chart ─────────────────────────────

    /** @param array<int, User> $people */
    private function activity(array $people): void
    {
        ActivityLog::whereIn('user_id', collect($people)->pluck('id'))->delete();

        // Vietnam hour => weight: a morning and an evening peak
        $weights = [7 => 2, 8 => 4, 9 => 9, 10 => 10, 11 => 7, 12 => 3, 13 => 4, 14 => 6, 15 => 5, 16 => 3, 19 => 4, 20 => 8, 21 => 9, 22 => 6, 23 => 2];
        $rows = [];
        $n = 0;
        foreach ($weights as $hour => $weight) {
            foreach (range(1, $weight * 3) as $k) {
                $user = $people[1 + (($n++ * 7) % 24)];
                $day = $n % 7;
                $at = now('Asia/Ho_Chi_Minh')->subDays($day)->setTime($hour, ($k * 11) % 60)->utc();
                $rows[] = ['user_id' => $user->id, 'user_name' => $user->name, 'event_type' => 'login', 'description' => 'Đăng nhập: '.$user->name, 'properties' => null, 'ip_address' => '203.0.113.10', 'created_at' => $at];
            }
        }
        ActivityLog::insert($rows);
    }

    /** @param array<int, User> $people */
    private function watchlists(array $people): void
    {
        foreach ($people as $i => $user) {
            foreach (array_slice(self::WATCH, 0, 1 + ($i * 3) % 7) as $symbol) {
                WatchlistItem::firstOrCreate(['user_id' => $user->id, 'symbol' => $symbol]);
            }
        }
    }

    // ── site settings and news ──────────────────────────────────────────────

    private function site(): void
    {
        // prepared but OFF: switch it on in Admin > Giao diện & Cache to see the bar on the public pages
        SiteSettings::set('announcement', ['enabled' => false, 'level' => 'warning', 'text' => 'Hệ thống bảo trì từ 23:00 đến 23:30 tối nay, một số chức năng có thể gián đoạn.', 'url' => '', 'link_text' => '']);
    }

    private function news(): void
    {
        $seeded = (array) SiteSettings::get('demo.seed', []);
        if ($seeded || News::query()->where('is_hidden', true)->orWhereNotNull('pinned_at')->exists()) {
            return;   // already done (or an admin already pinned / hid something: leave the real choices alone)
        }

        $latest = News::query()->orderByDesc('published_at')->limit(6)->get();
        if ($latest->count() < 5) {
            return;
        }
        $latest[3]->forceFill(['pinned_at' => now()])->save();   // an older article pinned to the top of the home page
        $latest[4]->forceFill(['is_hidden' => true])->save();    // one hidden from every public page
        SiteSettings::set('demo.seed', ['news_pinned' => $latest[3]->id, 'news_hidden' => $latest[4]->id]);
        Cache::forget('homepage_news');
    }

    // ── data-quality problems ───────────────────────────────────────────────

    /** @param array<int, User> $people */
    private function dataQuality(array $people): void
    {
        $fake = fn (string $symbol) => Stock::firstOrCreate(['symbol' => $symbol], ['name' => "{$symbol} (dữ liệu demo)", 'is_active' => false]);
        $price = fn (Stock $s, int $daysAgo, array $over = []) => StockPrice::firstOrCreate(
            ['stock_id' => $s->id, 'date' => now()->subDays($daysAgo)->toDateString()],
            $over + ['open' => 10, 'high' => 12, 'low' => 9, 'close' => 11, 'volume' => 1000],
        );

        $fake('DEMONOPX');                                   // tracked, no price at all
        $price($fake('DEMOOLD'), 60);                        // newest price 60 days old
        $bad = $fake('DEMOBAD');
        $price($bad, 1, ['close' => 0]);                     // impossible rows in the last 30 days
        $price($bad, 2, ['high' => 5, 'low' => 9]);

        WatchlistItem::firstOrCreate(['user_id' => $people[1]->id, 'symbol' => 'DEMOGHOST']);   // followed, but unknown to the system
        $portfolio = Portfolio::query()->orderBy('id')->first();
        if ($portfolio) {
            PortfolioItem::firstOrCreate(['portfolio_id' => $portfolio->id, 'stock_symbol' => 'DEMOPHANTOM'], [
                'stock_name' => 'DEMOPHANTOM', 'quantity' => 1, 'buy_price' => 1, 'current_price' => 1, 'buy_date' => now()->toDateString(),
            ]);
        }
    }

    private function summary(): void
    {
        $out = $this->command;
        if (! $out) {
            return;
        }
        $out->info('Đã tạo dữ liệu demo cho các tính năng admin. Đăng nhập /admin/login (mật khẩu chung: '.DemoUsersSeeder::PASSWORD.'):');
        $out->line('  admin@sunstock.test  (admin)        — thấy mọi thứ, tải/tạo sao lưu, chặn IP, xuất CSV');
        $out->line('  support@sunstock.test (adminsupport) — thấy AI, đồng bộ, trang chủ; KHÔNG thấy bảo mật, tên user ở trang AI bị ẩn');
        $out->line('  webadmin@sunstock.test (webadmin)    — chỉ xem timeline');
        $out->line('Xem: /admin (biểu đồ, quả chuông), /admin/sync-status, /admin/ai, /admin/site, /admin/health, /admin/security, /admin/users, /admin/news, /admin/data-quality');
        $out->warn('Xong thì dọn: php artisan db:seed --class=DemoAdminFeaturesCleanupSeeder (3 mã DEMO… cũng bị sync:stock-prices hằng ngày quét tới).');
    }

    /** Delete helper shared with the cleanup seeder. */
    public static function demoUserIds(): array
    {
        return User::query()->where('email', 'like', '%@'.self::DOMAIN)->pluck('id')->all();
    }

    public static function purgeStocks(): void
    {
        $ids = Stock::query()->whereIn('symbol', ['DEMONOPX', 'DEMOOLD', 'DEMOBAD'])->pluck('id');
        DB::table('stock_prices')->whereIn('stock_id', $ids)->delete();
        Stock::query()->whereIn('id', $ids)->delete();
    }
}
