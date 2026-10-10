<?php

namespace Tests\Feature\Seeders;

use App\Backend\Services\SecurityService;
use App\Models\Portfolio;
use App\Models\PortfolioItem;
use App\Models\PortfolioTransaction;
use App\Models\Stock;
use App\Models\StockPrice;
use App\Models\User;
use App\Support\PriceUnit;
use Database\Seeders\DemoBulkDataCleanupSeeder;
use Database\Seeders\DemoBulkDataSeeder;
use Database\Seeders\DemoUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\BuildsMarketPayload;
use Tests\TestCase;

/**
 * The bulk demo seeder (a few hundred realistic rows per feature): it must build consistent portfolios out of REAL stored prices on REAL dates,
 * never write the future, give every feature something to show, repeat itself exactly, and be removable. A smaller crowd is used here.
 */
class DemoBulkDataSeederTest extends TestCase
{
    use BuildsMarketPayload, RefreshDatabase;

    private const SYMBOLS = ['VCB', 'BID', 'CTG', 'TCB', 'MBB', 'ACB', 'VPB', 'FPT', 'VNM', 'HPG', 'MWG', 'SSI', 'VHM', 'VIC', 'GAS', 'PLX', 'E1VFVN30', 'FUESSV30'];

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Cache::flush();
        DemoBulkDataSeeder::$members = 36;

        $quotes = [];
        foreach (self::SYMBOLS as $i => $symbol) {
            $stock = Stock::create(['symbol' => $symbol, 'name' => $symbol]);
            $rows = [];
            foreach (range(0, 200) as $d) {
                $date = now()->subDays($d);
                if ($date->isWeekend()) {
                    continue;
                }
                $close = 20 + $i + sin($d / 9) * 3;
                $rows[] = ['stock_id' => $stock->id, 'date' => $date->toDateString(), 'open' => $close, 'high' => $close + 1, 'low' => $close - 1, 'close' => $close, 'volume' => 1000];
            }
            StockPrice::insert($rows);
            $quotes[$symbol] = [20000, 20000, 0.0, 1000, (100 - $i) * 1_000_000_000, 21000, 19000, 20000, 20500, 19500, 'HOSE'];
        }
        $this->seedMarketSnapshot(['quotes' => $quotes]);
    }

    protected function tearDown(): void
    {
        DemoBulkDataSeeder::$members = 320;
        parent::tearDown();
    }

    private function counts(): array
    {
        return array_map(fn (string $t) => DB::table($t)->count(), ['users', 'portfolios', 'portfolio_items', 'portfolio_transactions', 'watchlist_items', 'activity_logs', 'login_attempts', 'ai_requests', 'sync_runs', 'queue_job_logs', 'blocked_ips']);
    }

    #[Group('demoBulkSeeder')]
    public function test_every_feature_gets_rows_and_the_members_look_like_members(): void
    {
        $this->seed(DemoBulkDataSeeder::class);

        $counts = array_combine(['users', 'portfolios', 'items', 'trades', 'watchlist', 'activity', 'logins', 'ai', 'sync', 'jobs', 'blocked'], $this->counts());
        foreach (['portfolios', 'items', 'trades', 'watchlist', 'activity', 'logins', 'ai', 'sync', 'jobs'] as $feature) {
            $this->assertGreaterThan(20, $counts[$feature], "{$feature} has a decent amount of rows");
        }
        $this->assertSame(12, $counts['blocked']);

        $members = User::where('email', 'like', '%@'.DemoBulkDataSeeder::DOMAIN)->get();
        $this->assertCount(36, $members);
        $this->assertGreaterThan($members->count() * 0.6, $members->where('status', User::STATUS_ACTIVE)->count());
        $this->assertTrue(Hash::check(DemoUsersSeeder::PASSWORD, $members->first()->password));
        $this->assertSame($members->count(), $members->pluck('email')->unique()->count());
        $this->assertSame(0, $members->where('status', User::STATUS_PENDING)->filter(fn ($u) => $u->email_verified_at !== null)->count(), 'pending accounts are unverified');
        $this->assertSame(0, DB::table('user_profiles')->whereNotIn('user_id', $members->pluck('id'))->count());
    }

    #[Group('demoBulkSeeder')]
    public function test_portfolios_are_built_from_the_real_stored_prices_on_the_real_dates(): void
    {
        $this->seed(DemoBulkDataSeeder::class);

        $trades = PortfolioTransaction::query()->join('portfolios', 'portfolios.id', '=', 'portfolio_transactions.portfolio_id')->where('portfolios.description', 'like', '%DemoBulkDataSeeder%')->get(['portfolio_transactions.*']);
        $this->assertGreaterThan(20, $trades->count());

        foreach ($trades as $trade) {
            $stock = Stock::firstWhere('symbol', $trade->stock_symbol);
            $bar = StockPrice::where('stock_id', $stock->id)->where('date', '<=', $trade->traded_at->toDateString())->orderByDesc('date')->first();
            $this->assertNotNull($bar, "{$trade->stock_symbol} had a stored price on or before {$trade->traded_at->toDateString()}");
            $this->assertEqualsWithDelta(PriceUnit::toVnd($bar->close), $trade->price, 1.0, "{$trade->type} {$trade->stock_symbol} on {$trade->traded_at->toDateString()} is at the stored close");
        }
    }

    #[Group('demoBulkSeeder')]
    public function test_every_holding_equals_its_buys_minus_its_sells_and_has_a_price(): void
    {
        $this->seed(DemoBulkDataSeeder::class);

        $items = PortfolioItem::query()->join('portfolios', 'portfolios.id', '=', 'portfolio_items.portfolio_id')->where('portfolios.description', 'like', '%DemoBulkDataSeeder%')->get(['portfolio_items.*']);
        $this->assertGreaterThan(20, $items->count());

        foreach ($items as $item) {
            $net = PortfolioTransaction::where(['portfolio_id' => $item->portfolio_id, 'stock_symbol' => $item->stock_symbol])->get()
                ->sum(fn ($t) => $t->type === PortfolioTransaction::TYPE_BUY ? $t->quantity : -$t->quantity);
            $this->assertSame($net, (int) $item->quantity, "{$item->stock_symbol} quantity follows the ledger");
            $this->assertGreaterThan(0, $item->quantity);
            $this->assertNotNull($item->current_price, "{$item->stock_symbol} has a current price");
        }
    }

    #[Group('demoBulkSeeder')]
    public function test_nothing_happens_before_the_member_joined_or_in_the_future(): void
    {
        $this->seed(DemoBulkDataSeeder::class);

        foreach (['activity_logs', 'login_attempts', 'ai_requests', 'sync_runs'] as $table) {
            $column = $table === 'sync_runs' ? 'ran_at' : 'created_at';
            $this->assertSame(0, DB::table($table)->where($column, '>', now()->addMinute())->count(), "{$table} has no future rows");
        }
        foreach (Portfolio::where('description', 'like', '%DemoBulkDataSeeder%')->get() as $portfolio) {
            $joined = User::find($portfolio->user_id)->created_at->toDateString();
            $first = PortfolioTransaction::where('portfolio_id', $portfolio->id)->min('traded_at');
            $this->assertGreaterThanOrEqual($joined, substr((string) $first, 0, 10), 'the first trade is on or after the day the member signed up');
        }
    }

    #[Group('demoBulkSeeder')]
    public function test_the_security_page_has_exactly_the_guessers_it_should(): void
    {
        $this->seed(DemoBulkDataSeeder::class);

        $suspicious = app(SecurityService::class)->suspiciousIps();

        $this->assertCount(3, $suspicious);
        $this->assertSame(0, $suspicious->filter(fn ($row) => $row->failures < SecurityService::SUSPICIOUS_FAILURES)->count());
        $this->assertSame(0, DB::table('blocked_ips')->whereIn('ip', $suspicious->pluck('ip'))->count(), 'the ones at work right now are not blocked yet');
    }

    #[Group('demoBulkSeeder')]
    public function test_blocked_accounts_cannot_use_the_ai_and_their_attempts_were_refused(): void
    {
        $this->seed(DemoBulkDataSeeder::class);

        $blocked = User::where('email', 'like', '%@'.DemoBulkDataSeeder::DOMAIN)->whereNotNull('ai_blocked_at')->pluck('id');
        $this->assertGreaterThanOrEqual(1, $blocked->count());
        $this->assertLessThanOrEqual(3, $blocked->count());
        $this->assertGreaterThan(0, DB::table('ai_requests')->whereIn('user_id', $blocked)->where('status', 'refused')->count());
        $this->assertSame(0, DB::table('ai_requests')->where('status', 'refused')->whereNotIn('user_id', $blocked)->count(), 'only blocked accounts are refused');
        $this->assertSame(0, DB::table('ai_requests')->where('status', 'ok')->whereNull('model')->count(), 'every answer names its model');
    }

    #[Group('demoBulkSeeder')]
    public function test_running_it_twice_gives_exactly_the_same_data(): void
    {
        $this->seed(DemoBulkDataSeeder::class);
        $first = $this->counts();
        $emails = User::where('email', 'like', '%@'.DemoBulkDataSeeder::DOMAIN)->orderBy('email')->pluck('email')->all();

        $this->seed(DemoBulkDataSeeder::class);

        $this->assertSame($first, $this->counts());
        $this->assertSame($emails, User::where('email', 'like', '%@'.DemoBulkDataSeeder::DOMAIN)->orderBy('email')->pluck('email')->all());
    }

    #[Group('demoBulkSeeder')]
    public function test_the_cleanup_removes_it_all_and_leaves_everything_else_alone(): void
    {
        $other = User::factory()->create(['email' => 'someone@example.com']);
        DB::table('login_attempts')->insert(['email' => 'real@example.com', 'ip' => '198.51.100.1', 'user_agent' => 'Real browser', 'success' => true, 'door' => 'web', 'created_at' => now()]);
        $before = $this->counts();
        $this->seed(DemoBulkDataSeeder::class);

        $this->seed(DemoBulkDataCleanupSeeder::class);

        $after = $this->counts();
        $this->assertSame($before[1], $after[1], 'portfolios');
        $this->assertSame($before[3], $after[3], 'transactions');
        $this->assertSame(0, User::where('email', 'like', '%@'.DemoBulkDataSeeder::DOMAIN)->count());
        $this->assertSame(1, DB::table('login_attempts')->count(), 'only the unrelated real sign-in is left');
        $this->assertSame(0, DB::table('sync_runs')->count());
        $this->assertSame(0, DB::table('queue_job_logs')->count());
        $this->assertSame(0, DB::table('blocked_ips')->count());
        $this->assertNotNull(User::find($other->id));
    }

    #[Group('demoBulkSeeder')]
    public function test_it_refuses_to_run_outside_local_and_testing(): void
    {
        $this->app['env'] = 'production';

        $this->app->make(DemoBulkDataSeeder::class)->run();
        $this->app->make(DemoBulkDataCleanupSeeder::class)->run();

        $this->assertSame(0, User::count());
    }
}
