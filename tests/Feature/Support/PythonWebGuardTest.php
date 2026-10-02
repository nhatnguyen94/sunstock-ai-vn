<?php

namespace Tests\Feature\Support;

use App\Models\User;
use App\Support\PythonRunner;
use App\Support\PythonWebGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Python processes started by a visitor's request are limited (config/python_limits.php): a global ceiling on how many
 * run at once, and a per-visitor budget counted only for processes that really start. Console runs are never limited.
 */
class PythonWebGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'python_limits.web.enforce_in_console' => true,   // PHPUnit is a console run: switch the web rules on
            'python_limits.web.max_concurrent' => 2,
            'python_limits.web.guest.per_minute' => 4,
            'python_limits.web.guest.per_hour' => 20,
            'python_limits.web.user.per_minute' => 10,
            'python_limits.web.user.per_hour' => 60,
        ]);
    }

    private function fromIp(string $ip): void
    {
        $this->app['request']->server->set('REMOTE_ADDR', $ip);
    }

    /** Enter and immediately release, as a quick process would. */
    private function quickRun(): bool
    {
        $release = PythonWebGuard::enter(30);
        $release?->__invoke();

        return $release !== null;
    }

    // ── when it applies ─────────────────────────────────────────────────────

    #[Group('webPythonGuard')]
    public function test_console_runs_are_not_limited_unless_a_test_switches_the_rules_on(): void
    {
        config(['python_limits.web.enforce_in_console' => false]);

        $this->assertFalse(PythonWebGuard::applies());

        config(['python_limits.web.enforce_in_console' => true]);
        $this->assertTrue(PythonWebGuard::applies());
    }

    #[Group('webPythonGuard')]
    public function test_a_queue_worker_style_run_goes_through_even_when_every_web_slot_and_budget_is_used_up(): void
    {
        config(['python_limits.web.enforce_in_console' => false]);
        $slots = [Cache::lock('python-web-slot:1', 60), Cache::lock('python-web-slot:2', 60)];
        foreach ($slots as $lock) {
            $this->assertTrue($lock->get());
        }
        for ($i = 0; $i < 50; $i++) {
            RateLimiter::hit('python-web:min:ip:127.0.0.1', 60);
        }

        $result = PythonRunner::run(base_path('tests/Fixtures/python/sleep_and_print.py'), [0], 5);

        $this->assertSame(0, $result['exit_code']);
        $this->assertArrayNotHasKey('refused', $result);
    }

    // ── concurrency ─────────────────────────────────────────────────────────

    #[Group('webPythonGuard')]
    public function test_only_the_configured_number_of_processes_may_run_at_once_and_a_slot_comes_back_on_release(): void
    {
        $a = PythonWebGuard::enter(30);
        $b = PythonWebGuard::enter(30);
        $c = PythonWebGuard::enter(30);

        $this->assertNotNull($a);
        $this->assertNotNull($b);
        $this->assertNull($c, 'the third concurrent process must be refused');

        $a();
        $this->assertNotNull($d = PythonWebGuard::enter(30), 'a released slot is available again');
        $this->assertNull(PythonWebGuard::enter(30));

        $b();
        $d();
    }

    #[Group('webPythonGuard')]
    public function test_a_slot_held_by_a_process_that_died_frees_itself_after_the_run_ceiling(): void
    {
        $this->assertNotNull(PythonWebGuard::enter(30));
        $this->assertNotNull(PythonWebGuard::enter(30));   // both slots taken and never released: the "worker" crashed
        $this->assertNull(PythonWebGuard::enter(30));

        $this->travel(46)->seconds();   // 30 s ceiling + 15 s margin

        $this->assertNotNull(PythonWebGuard::enter(30));
    }

    // ── per-visitor budget ──────────────────────────────────────────────────

    #[Group('webPythonGuard')]
    public function test_a_guest_gets_four_processes_a_minute_then_is_refused_without_the_refusal_costing_anything(): void
    {
        $this->fromIp('203.0.113.7');

        foreach (range(1, 4) as $i) {
            $this->assertTrue($this->quickRun(), "run $i");
        }
        foreach (range(1, 5) as $i) {
            $this->assertFalse($this->quickRun(), 'refused attempts stay refused');
        }

        $this->travel(61)->seconds();
        $this->assertTrue($this->quickRun(), 'the minute budget refills');
    }

    #[Group('webPythonGuard')]
    public function test_the_hourly_budget_stops_a_slow_but_steady_guest(): void
    {
        $this->fromIp('203.0.113.8');

        for ($minute = 0; $minute < 5; $minute++) {
            foreach (range(1, 4) as $i) {
                $this->assertTrue($this->quickRun(), "minute $minute run $i");
            }
            $this->travel(61)->seconds();
        }

        $this->assertFalse($this->quickRun(), '20 in the hour is the cap even though every minute was within its own budget');
    }

    #[Group('webPythonGuard')]
    public function test_guests_are_counted_per_address_and_one_address_cannot_use_up_another_ones_budget(): void
    {
        $this->fromIp('203.0.113.9');
        foreach (range(1, 4) as $i) {
            $this->quickRun();
        }
        $this->assertFalse($this->quickRun());

        $this->fromIp('203.0.113.10');
        $this->assertTrue($this->quickRun());
    }

    #[Group('webPythonGuard')]
    public function test_a_signed_in_user_has_a_bigger_budget_that_follows_the_account_not_the_address(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        foreach (range(1, 10) as $i) {
            $this->fromIp('198.51.100.'.$i);   // a different address every time
            $this->assertTrue($this->quickRun(), "user run $i");
        }
        $this->fromIp('198.51.100.99');
        $this->assertFalse($this->quickRun(), 'the 11th in a minute is refused wherever it comes from');

        // the guests behind those addresses are untouched
        $this->app['auth']->forgetGuards();
        $this->app['request']->setUserResolver(fn () => null);
        $this->fromIp('198.51.100.1');
        $this->assertTrue($this->quickRun());
    }

    #[Group('webPythonGuard')]
    public function test_a_refusal_because_every_slot_is_busy_does_not_use_the_visitors_budget(): void
    {
        $this->fromIp('203.0.113.11');
        $a = PythonWebGuard::enter(30);
        $b = PythonWebGuard::enter(30);   // 2 of the guest's 4 used, both slots busy

        foreach (range(1, 10) as $i) {
            $this->assertNull(PythonWebGuard::enter(30));   // refused for lack of a slot, nothing is charged
        }
        $a();
        $b();

        $this->assertTrue($this->quickRun());
        $this->assertTrue($this->quickRun());
        $this->assertFalse($this->quickRun(), 'exactly the 4 that really started were counted');
    }

    // ── PythonRunner integration ────────────────────────────────────────────

    #[Group('webPythonGuard')]
    public function test_python_runner_returns_a_refused_result_and_starts_nothing_when_the_guard_says_no(): void
    {
        $this->fromIp('203.0.113.12');
        foreach (range(1, 4) as $i) {
            $this->quickRun();
        }

        $result = PythonRunner::run(base_path('tests/Fixtures/python/sleep_and_print.py'), [0], 5);

        $this->assertSame([], $result['output']);
        $this->assertSame(PythonWebGuard::EXIT_REFUSED, $result['exit_code']);
        $this->assertFalse($result['timed_out']);
        $this->assertTrue($result['refused']);
        $this->assertNull(PythonRunner::runAndDecodeJson(base_path('tests/Fixtures/python/sleep_and_print.py'), [0], 5));
    }

    #[Group('webPythonGuard')]
    public function test_python_runner_runs_normally_when_allowed_and_gives_the_slot_back_even_after_a_timeout(): void
    {
        $this->fromIp('203.0.113.13');
        config(['python_limits.web.max_concurrent' => 1]);

        $ok = PythonRunner::run(base_path('tests/Fixtures/python/sleep_and_print.py'), [0], 5);
        $this->assertSame(0, $ok['exit_code']);
        $this->assertArrayNotHasKey('refused', $ok);

        $killed = PythonRunner::run(base_path('tests/Fixtures/python/sleep_and_print.py'), [3], 1);
        $this->assertTrue($killed['timed_out']);

        $this->assertNotNull($slot = PythonWebGuard::enter(30), 'the single slot was released after both runs');
        $slot();
    }

    // ── configuration ───────────────────────────────────────────────────────

    #[Group('webPythonGuard')]
    public function test_the_defaults_and_the_documentation_of_the_env_variables(): void
    {
        $file = require base_path('config/python_limits.php');

        $this->assertSame(3, $file['web']['max_concurrent']);
        $this->assertSame(['per_minute' => 4, 'per_hour' => 20], $file['web']['guest']);
        $this->assertSame(['per_minute' => 10, 'per_hour' => 60], $file['web']['user']);
        $this->assertFalse($file['web']['enforce_in_console']);

        $example = file_get_contents(base_path('.env.example'));
        foreach (['PYTHON_WEB_MAX_CONCURRENT=3', 'PYTHON_WEB_GUEST_PER_MINUTE=4', 'PYTHON_WEB_GUEST_PER_HOUR=20', 'PYTHON_WEB_USER_PER_MINUTE=10', 'PYTHON_WEB_USER_PER_HOUR=60'] as $line) {
            $this->assertStringContainsString($line, $example);
        }
    }

    #[Group('webPythonGuard')]
    public function test_values_below_one_are_raised_to_one_so_a_typo_cannot_switch_a_guard_off(): void
    {
        $names = ['PYTHON_WEB_MAX_CONCURRENT', 'PYTHON_WEB_GUEST_PER_MINUTE', 'PYTHON_WEB_GUEST_PER_HOUR', 'PYTHON_WEB_USER_PER_MINUTE', 'PYTHON_WEB_USER_PER_HOUR'];
        foreach ($names as $name) {
            putenv("{$name}=0");
            $_ENV[$name] = '-3';
        }

        try {
            $file = require base_path('config/python_limits.php');
        } finally {
            foreach ($names as $name) {
                putenv($name);
                unset($_ENV[$name]);
            }
        }

        $this->assertSame(1, $file['web']['max_concurrent']);
        $this->assertSame(1, $file['web']['guest']['per_minute']);
        $this->assertSame(1, $file['web']['guest']['per_hour']);
        $this->assertSame(1, $file['web']['user']['per_minute']);
        $this->assertSame(1, $file['web']['user']['per_hour']);
    }
}
