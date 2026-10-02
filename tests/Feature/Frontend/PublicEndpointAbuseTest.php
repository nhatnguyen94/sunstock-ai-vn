<?php

namespace Tests\Feature\Frontend;

use App\Frontend\Services\ExchangeRateService;
use App\Models\ExchangeRate;
use App\Models\StockSymbol;
use App\Models\User;
use App\Support\PythonRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Anonymous abuse of the public endpoints that can start a Python process. A tiny executable stands in for Python and
 * writes one line per start, so the tests count the processes that REALLY started:
 *   - made-up symbols and malformed dates must never start one;
 *   - a flood of valid-looking requests may start only as many as the visitor's budget allows;
 *   - "refresh" buttons are for signed-in users;
 *   - a refused run never poisons a cache.
 */
class PublicEndpointAbuseTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    private string $log;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Queue::fake();
        Cache::flush();

        $this->dir = sys_get_temp_dir().'/py-abuse-'.uniqid();
        mkdir($this->dir);
        $this->log = $this->dir.'/spawns.log';
        $wrapper = $this->dir.'/fake-python.sh';
        file_put_contents($wrapper, "#!/bin/sh\necho started >> \"\$PYTHON_SPAWN_LOG\"\nexit 0\n");
        chmod($wrapper, 0755);
        putenv('PYTHON_SPAWN_LOG='.$this->log);

        config([
            'services.python.path' => $wrapper,
            'python_limits.web.enforce_in_console' => true,
            'python_limits.web.max_concurrent' => 3,
            'python_limits.web.guest.per_minute' => 4,
            'python_limits.web.guest.per_hour' => 20,
            'python_limits.web.user.per_minute' => 10,
            'python_limits.web.user.per_hour' => 60,
        ]);
    }

    protected function tearDown(): void
    {
        putenv('PYTHON_SPAWN_LOG');
        foreach (glob($this->dir.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function spawns(): int
    {
        return is_file($this->log) ? substr_count((string) file_get_contents($this->log), "started\n") : 0;
    }

    private function listed(int $count, string $prefix = 'LST'): array
    {
        $symbols = [];
        foreach (range(1, $count) as $i) {
            $symbols[] = $symbol = $prefix.str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            StockSymbol::create(['symbol' => $symbol, 'name' => "Công ty $symbol", 'exchange' => 'HSX']);
        }

        return $symbols;
    }

    private function seedLatestRates(): void
    {
        ExchangeRate::create(['currency_code' => 'USD', 'currency_name' => 'US DOLLAR', 'buy_cash' => '1', 'buy_transfer' => '1', 'sell' => '1', 'date' => now()->toDateString()]);
    }

    // ── company profile ─────────────────────────────────────────────────────

    #[Group('webPythonGuard')]
    public function test_thirty_made_up_symbols_cost_nothing_and_are_all_a_clean_404(): void
    {
        foreach (range(1, 30) as $i) {
            $this->postJson('/company/ZZ'.str_pad((string) $i, 3, '0', STR_PAD_LEFT).'/load')->assertNotFound()->assertJsonPath('success', false);
        }

        $this->assertSame(0, $this->spawns());
    }

    #[Group('webPythonGuard')]
    public function test_a_flood_of_real_symbols_from_one_guest_starts_only_as_many_processes_as_the_guest_budget(): void
    {
        $symbols = $this->listed(20);

        foreach ($symbols as $symbol) {
            $status = $this->postJson("/company/$symbol/load")->status();
            $this->assertContains($status, [502, 503], "$symbol -> $status");   // the (fake) source never answers
        }

        $this->assertSame(4, $this->spawns(), 'a guest may start 4 processes a minute, whatever the number of requests');
        foreach ($symbols as $symbol) {
            $this->assertFalse(Cache::has("company-profile-missing:$symbol"), 'a refused or failed run must not be remembered as "no such company"');
        }
    }

    #[Group('webPythonGuard')]
    public function test_a_signed_in_user_gets_the_bigger_budget(): void
    {
        $symbols = $this->listed(20);
        $this->actingAs(User::factory()->create());

        foreach ($symbols as $symbol) {
            $this->postJson("/company/$symbol/load");
        }

        $this->assertSame(10, $this->spawns());
    }

    #[Group('webPythonGuard')]
    public function test_the_budget_refills_so_a_real_visitor_is_not_locked_out_for_good(): void
    {
        $symbols = $this->listed(8);
        foreach (array_slice($symbols, 0, 6) as $symbol) {
            $this->postJson("/company/$symbol/load");
        }
        $this->assertSame(4, $this->spawns());

        $this->travel(61)->seconds();
        $this->postJson('/company/'.$symbols[6].'/load');

        $this->assertSame(5, $this->spawns());
    }

    #[Group('webPythonGuard')]
    public function test_the_forced_refresh_of_a_company_needs_a_login_and_starts_nothing_for_a_guest(): void
    {
        [$symbol] = $this->listed(1);

        $this->postJson("/company/$symbol/load?force=1")->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'Vui lòng đăng nhập để làm mới dữ liệu.')
            ->assertJsonPath('login_url', route('login'));

        $this->assertSame(0, $this->spawns());
    }

    #[Group('webPythonGuard')]
    public function test_a_signed_in_user_may_force_a_refresh_but_only_of_a_listed_symbol(): void
    {
        [$symbol] = $this->listed(1);
        $this->actingAs(User::factory()->create());

        $this->postJson('/company/NOSUCH/load?force=1')->assertNotFound();
        $this->assertSame(0, $this->spawns(), 'the known-symbol check also guards the forced path');

        $this->postJson("/company/$symbol/load?force=1");
        $this->assertSame(1, $this->spawns());
    }

    // ── gold ────────────────────────────────────────────────────────────────

    #[Group('webPythonGuard')]
    public function test_the_gold_refresh_button_is_for_signed_in_users(): void
    {
        $this->postJson('/gold/refresh')->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Vui lòng đăng nhập để làm mới giá.')
            ->assertJsonPath('login_url', route('login'));

        $this->assertSame(0, $this->spawns());
        $this->assertFalse(Cache::has('gold-refresh-cooldown'), 'a guest must not even use up the shared cooldown');
    }

    // ── exchange rates ──────────────────────────────────────────────────────

    /** @return array<string, array{mixed}> */
    public static function unsearchableDates(): array
    {
        return [
            'letters' => ['abc'],
            'impossible month' => ['2026-13-45'],
            'february 31st' => ['2026-02-31'],
            'tomorrow' => [now()->addDay()->toDateString()],
            'far future' => ['2099-01-01'],
            'older than the limit' => [now()->subDays(ExchangeRateService::MAX_HISTORY_DAYS + 1)->toDateString()],
            'date with a trailing payload' => ['2026-01-01; rm -rf /'],
            'sql' => ["2026-01-01' OR '1'='1"],
            'datetime' => ['2026-01-01 10:00:00'],
            'slash format' => ['2026/01/01'],
            'two digit year' => ['26-01-01'],
            'fullwidth digits' => ['２０２６-０１-０１'],
            'newline in the middle' => ["2026-01\n-01"],
            'a very long string' => [str_repeat('9', 5000)],
            'array' => [['2026-01-01']],
        ];
    }

    #[Group('webPythonGuard')]
    #[DataProvider('unsearchableDates')]
    public function test_a_malformed_or_out_of_range_date_is_refused_with_a_message_before_cache_or_python(mixed $date): void
    {
        $this->seedLatestRates();

        $this->get('/exchange-rate/search?'.http_build_query(['date' => $date]))
            ->assertOk()
            ->assertSee('Ngày không hợp lệ');

        $this->assertSame(0, $this->spawns());
        if (is_string($date)) {
            $this->assertFalse(Cache::has("exchange_rates_$date"), 'no cache key may be built from the visitor\'s text');
        }
    }

    #[Group('webPythonGuard')]
    public function test_the_edges_of_the_searchable_range_are_accepted(): void
    {
        $this->assertTrue(ExchangeRateService::isSearchableDate(now()->toDateString()));
        $this->assertTrue(ExchangeRateService::isSearchableDate(now()->subDays(ExchangeRateService::MAX_HISTORY_DAYS)->toDateString()));
        $this->assertTrue(ExchangeRateService::isSearchableDate('2024-02-29'));   // a real leap day
        $this->assertFalse(ExchangeRateService::isSearchableDate('2025-02-29'));
        $this->assertFalse(ExchangeRateService::isSearchableDate(null));
        $this->assertFalse(ExchangeRateService::isSearchableDate(20260101));
        $this->assertFalse(ExchangeRateService::isSearchableDate(''));
    }

    #[Group('webPythonGuard')]
    public function test_thirty_different_valid_dates_start_only_as_many_processes_as_the_budget_and_a_refused_one_is_not_cached(): void
    {
        $this->seedLatestRates();
        $dates = [];
        foreach (range(10, 39) as $daysAgo) {
            $dates[] = now()->subDays($daysAgo)->toDateString();
        }

        foreach ($dates as $date) {
            $this->get("/exchange-rate/search?date=$date")->assertOk();
        }

        $this->assertSame(4, $this->spawns());
        $cached = array_filter($dates, fn ($d) => Cache::has("exchange_rates_$d"));
        $this->assertCount(4, $cached, 'only the 4 answers that really came from a run are remembered; the 26 refused ones are not');
    }

    #[Group('webPythonGuard')]
    public function test_a_refused_fetch_does_not_leave_the_latest_rates_empty_for_half_an_hour(): void
    {
        // no rates stored yet: the page needs one fetch. Use the budget up first so that fetch is refused.
        foreach (range(1, 4) as $i) {
            $this->get('/exchange-rate/search?date='.now()->subDays(100 + $i)->toDateString());
        }
        $spawnsBefore = $this->spawns();

        $this->get('/exchange-rate')->assertOk();

        $this->assertSame($spawnsBefore, $this->spawns(), 'refused, so nothing started');
        $this->assertFalse(Cache::has('exchange_rates_latest_3'), 'an empty answer must not be remembered');

        $this->travel(61)->seconds();
        $this->get('/exchange-rate')->assertOk();
        $this->assertGreaterThan($spawnsBefore, $this->spawns(), 'a minute later the fetch is attempted again');
    }

    #[Group('webPythonGuard')]
    public function test_a_stored_date_is_served_without_any_process_and_a_weekend_style_empty_answer_is_remembered(): void
    {
        $this->seedLatestRates();
        $stored = now()->subDays(20)->toDateString();
        ExchangeRate::create(['currency_code' => 'EUR', 'currency_name' => 'EURO', 'buy_cash' => '2', 'buy_transfer' => '2', 'sell' => '2', 'date' => $stored]);

        $this->get("/exchange-rate/search?date=$stored")->assertOk()->assertSee('EURO');
        $this->assertSame(0, $this->spawns());

        $empty = now()->subDays(21)->toDateString();
        $this->get("/exchange-rate/search?date=$empty")->assertOk();
        $this->assertSame(1, $this->spawns());
        $this->get("/exchange-rate/search?date=$empty")->assertOk();
        $this->assertSame(1, $this->spawns(), 'asking again for the same empty day is answered from the cache');
    }

    // ── guest vs console ────────────────────────────────────────────────────

    #[Group('webPythonGuard')]
    public function test_the_scheduler_and_artisan_are_not_subject_to_the_web_budget(): void
    {
        config(['python_limits.web.enforce_in_console' => false]);

        foreach (range(1, 12) as $i) {
            PythonRunner::run($this->dir.'/anything.py', [$i], 5);
        }

        $this->assertSame(12, $this->spawns());
    }
}
