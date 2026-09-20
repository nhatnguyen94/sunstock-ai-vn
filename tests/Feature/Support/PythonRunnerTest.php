<?php

namespace Tests\Feature\Support;

use App\Support\PythonRunner;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Genuinely spawns the fixture Python script (tests/Fixtures/python/sleep_and_print.py)
 * — this is the one thing that can't be faked/mocked here: the whole point of
 * PythonRunner is that it wraps a real OS-level `exec()` call with the Unix
 * `timeout` utility so a hung subprocess can never block the caller past the
 * configured ceiling. A test that mocks exec() would prove nothing about
 * whether that actually works. See docs/HISTORY.md, QUEUE_MONITOR_REALTIME_ACTIVITY
 * for the real bug this fixes — a job was still "processing" 9+ minutes after
 * its own declared timeout because plain exec() cannot be interrupted by
 * Laravel's pcntl-signal-based job timeout while blocked in a subprocess call.
 */
class PythonRunnerTest extends TestCase
{
    private function fixture(): string
    {
        return base_path('tests/Fixtures/python/sleep_and_print.py');
    }

    #[Group('pythonRunner')]
    public function test_run_returns_output_and_exit_code_for_a_fast_script(): void
    {
        $result = PythonRunner::run($this->fixture(), [0], 5);

        $this->assertSame(0, $result['exit_code']);
        $this->assertFalse($result['timed_out']);
        $this->assertStringContainsString('"ok": true', implode('', $result['output']));
    }

    #[Group('pythonRunner')]
    public function test_run_kills_a_script_that_exceeds_the_timeout(): void
    {
        $start = microtime(true);

        $result = PythonRunner::run($this->fixture(), [3], 1);

        $elapsed = microtime(true) - $start;

        $this->assertTrue($result['timed_out']);
        $this->assertSame(124, $result['exit_code']);
        // Proves the process was actually killed early, not just that we waited
        // out the full 3s sleep and happened to report timed_out=true afterward.
        $this->assertLessThan(2.5, $elapsed, 'PythonRunner did not actually kill the hung process early');
    }

    #[Group('pythonRunner')]
    public function test_the_configured_vnstock_api_key_reaches_the_python_process_environment(): void
    {
        // vnai only reads VNSTOCK_API_KEY from the environment. Artisan/queue processes usually inherit it from the
        // .env that Laravel loaded, but a PHP-FPM pool with clear_env does not — so the runner exports it itself.
        $this->withoutInheritedApiKey(function () {
            config(['services.vnstock.api_key' => 'test-key_ABC-123456']);

            $decoded = PythonRunner::runAndDecodeJson(base_path('tests/Fixtures/python/print_env.py'), [], 5);

            $this->assertSame(['key' => 'test-key_ABC-123456'], $decoded);
        });
    }

    #[Group('pythonRunner')]
    public function test_vnai_is_told_not_to_write_agent_instruction_files(): void
    {
        // vnai appended a "skill router" prompt to AGENTS.md (and machine-wide Claude/Codex/Gemini memory files) on every run
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('env prefix is only applied on POSIX');
        }

        $decoded = PythonRunner::runAndDecodeJson(base_path('tests/Fixtures/python/print_agent_env.py'), [], 5);

        $this->assertSame(['disable' => '1', 'targets' => 'none'], $decoded);
    }

    #[Group('pythonRunner')]
    public function test_no_api_key_is_passed_when_none_is_configured_or_it_looks_malformed(): void
    {
        $this->withoutInheritedApiKey(function () {
            foreach ([null, '', 'short', "bad key; rm -rf /"] as $value) {
                config(['services.vnstock.api_key' => $value]);

                $decoded = PythonRunner::runAndDecodeJson(base_path('tests/Fixtures/python/print_env.py'), [], 5);

                $this->assertSame(['key' => null], $decoded, 'key ' . var_export($value, true) . ' must not be exported');
            }
        });
    }

    /** Run `$fn` with VNSTOCK_API_KEY removed from this process's own environment (children would inherit it). */
    private function withoutInheritedApiKey(callable $fn): void
    {
        $inherited = getenv('VNSTOCK_API_KEY');
        putenv('VNSTOCK_API_KEY');
        try {
            $fn();
        } finally {
            if ($inherited !== false) {
                putenv('VNSTOCK_API_KEY=' . $inherited);
            }
        }
    }

    #[Group('pythonRunner')]
    public function test_run_and_decode_json_parses_the_last_json_line(): void
    {
        $decoded = PythonRunner::runAndDecodeJson($this->fixture(), [0], 5);

        $this->assertSame(['ok' => true, 'slept' => 0.0], $decoded);
    }

    #[Group('pythonRunner')]
    public function test_run_and_decode_json_returns_null_when_the_script_produced_no_json(): void
    {
        // Times out before printing anything — no JSON line to find.
        $decoded = PythonRunner::runAndDecodeJson($this->fixture(), [3], 1);

        $this->assertNull($decoded);
    }

    /**
     * PHP's exec() only ever captures stdout into $output, never stderr, with or
     * without suppressStderr — that flag's real effect is keeping stderr noise out
     * of wherever the PHP process's own stderr goes (container/server logs), not
     * out of $output, so it can't be asserted on through $output directly. What
     * IS worth guarding: a script writing to stderr must never corrupt stdout
     * capture / JSON parsing, regardless of the flag.
     */
    #[Group('pythonRunner')]
    public function test_stderr_output_never_corrupts_stdout_json_parsing(): void
    {
        $suppressed = PythonRunner::run($this->fixture(), [0, '--stderr-too'], 5, suppressStderr: true);
        $notSuppressed = PythonRunner::run($this->fixture(), [0, '--stderr-too'], 5, suppressStderr: false);

        $this->assertStringContainsString('"ok": true', implode('', $suppressed['output']));
        $this->assertStringContainsString('"ok": true', implode('', $notSuppressed['output']));
    }

    #[Group('pythonRunner')]
    public function test_home_is_redirected_to_a_writable_dir_when_the_users_home_is_not_writable(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('HOME override is POSIX-only (Windows dev setups run scripts unwrapped).');
        }

        // Reproduces the real bug: PHP-FPM runs as www-data whose home (/var/www) is root-owned, so vnstock
        // died with "[Errno 13] Permission denied: '/var/www/.vnstock'" on every web-triggered Python call.
        $original = getenv('HOME');
        putenv('HOME=/nonexistent/unwritable/home');

        try {
            $decoded = PythonRunner::runAndDecodeJson(base_path('tests/Fixtures/python/print_home.py'), [], 10);
        } finally {
            $original === false ? putenv('HOME') : putenv('HOME=' . $original);
        }

        $this->assertNotNull($decoded);
        $this->assertNotSame('/nonexistent/unwritable/home', $decoded['home']);
        $this->assertTrue(is_writable($decoded['home']), "Python's HOME ({$decoded['home']}) must be writable");
    }

    #[Group('pythonRunner')]
    public function test_a_writable_home_is_left_alone(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('HOME override is POSIX-only.');
        }

        $home = sys_get_temp_dir();   // writable by definition
        $original = getenv('HOME');
        putenv('HOME=' . $home);

        try {
            $decoded = PythonRunner::runAndDecodeJson(base_path('tests/Fixtures/python/print_home.py'), [], 10);
        } finally {
            $original === false ? putenv('HOME') : putenv('HOME=' . $original);
        }

        $this->assertSame($home, $decoded['home']);
    }
}
