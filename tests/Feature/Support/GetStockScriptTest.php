<?php

namespace Tests\Feature\Support;

use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Runs the REAL py/get_stock.py against a local fake of KBS's history endpoint (`php -S` + tests/Fixtures/kbs_router.php)
 * — the script is the core of the stock page's speed-up, and mocking it would prove nothing about its parsing, units
 * or fallback behaviour. No network, no vnstock: STOCK_SOURCES is limited to the direct KBS source.
 */
class GetStockScriptTest extends TestCase
{
    /** @var resource|null */
    private $server = null;
    private int $port = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->port = random_int(20000, 40000);
        $router = base_path('tests/Fixtures/kbs_router.php');
        $this->server = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:{$this->port}", $router],
            [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes
        );

        // wait (up to ~3 s) until the fake answers
        for ($i = 0; $i < 30; $i++) {
            if (@fsockopen('127.0.0.1', $this->port, $errno, $errstr, 0.1)) {
                return;
            }
            usleep(100_000);
        }
        $this->markTestSkipped('could not start the fake KBS server');
    }

    protected function tearDown(): void
    {
        if (is_resource($this->server)) {
            proc_terminate($this->server);
            proc_close($this->server);
        }
        parent::tearDown();
    }

    /** @return array{data: array, errors: array, sources: array}|null */
    private function script(string $symbols, array $args = [], string $sources = 'KBS-direct', ?float &$seconds = null): ?array
    {
        $env = 'KBS_BASE_URL=' . escapeshellarg("http://127.0.0.1:{$this->port}") . ' STOCK_SOURCES=' . escapeshellarg($sources) . ' STOCK_DIRECT_TIMEOUT=5 ';
        $cmd = $env . escapeshellarg((string) config('services.python.path', 'python')) . ' ' . escapeshellarg(base_path('py/get_stock.py')) . ' ' . escapeshellarg($symbols);
        foreach ($args as $a) {
            $cmd .= ' ' . escapeshellarg($a);
        }

        $t = microtime(true);
        exec($cmd . ' 2>/dev/null', $out);
        $seconds = microtime(true) - $t;

        for ($i = count($out) - 1; $i >= 0; $i--) {
            if (str_starts_with(trim($out[$i]), '{')) {
                return json_decode(trim($out[$i]), true);
            }
        }

        return null;
    }

    #[Group('stockFreshness')]
    public function test_stock_bars_come_back_oldest_first_in_thousands_with_a_date(): void
    {
        $r = $this->script('FPT', ['2026-08-01', '2026-09-20']);

        $bars = $r['data']['FPT'];
        $this->assertSame([], $r['errors']);
        $this->assertSame(['FPT' => 'KBS-direct'], $r['sources']);
        $this->assertSame(['2026-09-01', '2026-09-17', '2026-09-18'], array_column($bars, 'date'));   // ascending
        $last = $bars[2];
        $this->assertSame(74.5, $last['open']);          // 74,500 VND -> 74.5 (the database keeps thousands)
        $this->assertSame(74.8, $last['high']);
        $this->assertSame(71.7, $last['low']);
        $this->assertSame(71.7, $last['close']);
        $this->assertSame(15500700, $last['volume']);
        $this->assertSame(strtotime('2026-09-18 07:00:00 UTC') * 1000, $last['time']);   // epoch ms, the legacy field
    }

    #[Group('stockFreshness')]
    public function test_the_start_date_trims_older_bars(): void
    {
        $r = $this->script('FPT', ['2026-09-10']);

        $this->assertSame(['2026-09-17', '2026-09-18'], array_column($r['data']['FPT'], 'date'));
    }

    #[Group('stockFreshness')]
    public function test_an_index_keeps_its_points_and_uses_the_index_endpoint(): void
    {
        $r = $this->script('VNINDEX', ['2026-09-10']);

        $this->assertSame(1815.66, $r['data']['VNINDEX'][0]['close']);      // not divided by 1000
        $this->assertSame('2026-09-18', $r['data']['VNINDEX'][0]['date']);
    }

    #[Group('stockFreshness')]
    public function test_several_symbols_in_one_run_and_a_failing_one_does_not_hide_the_others(): void
    {
        $r = $this->script('FPT,VNINDEX,NOPE', ['2026-09-10']);

        $this->assertSame(['FPT', 'VNINDEX'], array_keys($r['data']));
        $this->assertArrayHasKey('NOPE', $r['errors']);
        $this->assertStringContainsString('404', $r['errors']['NOPE']);
    }

    #[Group('stockFreshness')]
    public function test_an_empty_direct_answer_is_authoritative_and_does_not_fall_back_to_the_slow_sources(): void
    {
        // If it fell back, vnstock/VCI would be imported and called (seconds, network). A quiet window must be instant.
        $r = $this->script('QUIET', ['2026-09-10'], 'KBS-direct,VCI', $seconds);

        $this->assertSame(['QUIET' => []], $r['data']);
        $this->assertSame([], $r['errors']);
        $this->assertSame([], $r['sources']);
        $this->assertLessThan(3.0, $seconds, 'an empty KBS answer must not trigger the vnstock fallback');
    }
}
