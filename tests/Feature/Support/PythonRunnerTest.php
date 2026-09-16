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
}
