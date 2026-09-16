<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Runs a Python script and guarantees the calling PHP process (queue worker,
 * web request, or artisan command) is never blocked past $timeoutSeconds —
 * even if the script itself hangs (e.g. an external API that never responds).
 *
 * Why this exists: every Python call in this app used a plain `exec()`, whose
 * blocking read cannot be reliably interrupted by Laravel's own job/worker
 * `--timeout` — that mechanism relies on a pcntl SIGALRM being delivered and
 * handled by the PHP process, which does not happen while PHP is itself
 * blocked inside exec()'s underlying syscall. Confirmed as a real bug: a
 * `ProcessStockPriceSync` job (declared `$timeout = 300`) was still running
 * 9+ minutes in because trading.vietcap.com.vn was hanging on every request —
 * Laravel's timeout never fired, so the worker never got its slot back.
 *
 * Fix: wrap the command with the Unix `timeout` utility (GNU coreutils,
 * present in the Docker image — see docker/php/Dockerfile, `php:8.2-fpm` is
 * Debian-based). The OS kills the Python child directly if it runs too long,
 * which makes exec()'s pipe hit EOF and return immediately — no dependency on
 * PHP signal handling at all.
 *
 * Windows (XAMPP/manual dev setup) has no `timeout` utility with this
 * behavior, so this class falls back to a plain, unwrapped exec() there —
 * same as before this fix, not worse. Docker (the recommended setup, and
 * where this app actually runs in production) gets the real fix.
 */
class PythonRunner
{
    /**
     * @param string $scriptPath Absolute path to the .py file (base_path('py/...'))
     * @param array<int, string|int> $args Positional arguments, shell-escaped individually
     * @param int $timeoutSeconds Hard ceiling — must stay comfortably below whatever
     *                            timeout (job $timeout, worker --timeout, PHP max_execution_time)
     *                            governs the calling context, so a legitimate timeout here still
     *                            leaves room to log/return cleanly instead of getting killed mid-return.
     * @param bool $suppressStderr Redirect stderr away from $output (some scripts print warnings
     *                             to stderr that would otherwise pollute the JSON-scan below)
     * @return array{output: string[], exit_code: int, timed_out: bool}
     */
    public static function run(string $scriptPath, array $args, int $timeoutSeconds, bool $suppressStderr = false): array
    {
        $pythonPath = config('services.python.path', 'python');

        $command = PHP_OS_FAMILY === 'Windows'
            ? self::buildCommand($pythonPath, $scriptPath, $args)
            : 'timeout ' . escapeshellarg((string) $timeoutSeconds) . ' ' . self::buildCommand($pythonPath, $scriptPath, $args);

        if ($suppressStderr) {
            $command .= PHP_OS_FAMILY === 'Windows' ? ' 2>NUL' : ' 2>/dev/null';
        }

        exec($command, $output, $exitCode);

        // `timeout` exits 124 specifically when it had to kill the child — distinguish
        // that from a normal Python error so callers/logs can tell "hung" from "crashed".
        $timedOut = $exitCode === 124;

        if ($timedOut) {
            Log::warning('PythonRunner: script killed after exceeding timeout', [
                'script' => basename($scriptPath),
                'timeout_seconds' => $timeoutSeconds,
            ]);
        }

        return ['output' => $output, 'exit_code' => $exitCode, 'timed_out' => $timedOut];
    }

    /**
     * Same as run(), but also does the "scan output backward for the first
     * JSON-looking line" parsing every caller in this app repeats — see
     * docs/PYTHON_INTEGRATION.md. Returns null if no JSON line was found.
     */
    public static function runAndDecodeJson(string $scriptPath, array $args, int $timeoutSeconds): ?array
    {
        $result = self::run($scriptPath, $args, $timeoutSeconds);

        for ($i = count($result['output']) - 1; $i >= 0; $i--) {
            $line = trim($result['output'][$i]);
            if (str_starts_with($line, '{') || str_starts_with($line, '[')) {
                $decoded = json_decode($line, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
            }
        }

        return null;
    }

    private static function buildCommand(string $pythonPath, string $scriptPath, array $args): string
    {
        $command = escapeshellarg($pythonPath) . ' ' . escapeshellarg($scriptPath);

        foreach ($args as $arg) {
            $command .= ' ' . escapeshellarg((string) $arg);
        }

        return $command;
    }
}
