<?php

/*
|--------------------------------------------------------------------------
| Limits for Python processes started from a WEB REQUEST
|--------------------------------------------------------------------------
|
| Queue workers, the scheduler and artisan commands are not limited here: only a Python run that a visitor's request
| starts (a first-ever page view, "refresh" buttons, a date search...). Cached pages never reach this code.
|
| Two independent guards (App\Support\PythonWebGuard):
|   - a global ceiling on how many such processes may run AT THE SAME TIME, so a burst of cache misses (or an attack)
|     cannot fill the server with 60-second subprocesses;
|   - a budget per visitor (signed-in user id, otherwise IP) counted only when a process really starts.
|
| A refused call behaves like an unreachable data source: the page shows its usual "try again in a moment" message and
| nothing wrong is cached. Change the numbers in .env:
|
|   PYTHON_WEB_MAX_CONCURRENT        processes at once, all visitors together       (default 3)
|   PYTHON_WEB_GUEST_PER_MINUTE      per guest IP                                    (default 4)
|   PYTHON_WEB_GUEST_PER_HOUR        per guest IP                                    (default 20)
|   PYTHON_WEB_USER_PER_MINUTE       per signed-in user                              (default 10)
|   PYTHON_WEB_USER_PER_HOUR         per signed-in user                              (default 60)
|
| Every value is floored at 1, so a typo can never switch a guard off.
|
*/

return [
    'web' => [
        'max_concurrent' => max(1, (int) env('PYTHON_WEB_MAX_CONCURRENT', 3)),

        'guest' => [
            'per_minute' => max(1, (int) env('PYTHON_WEB_GUEST_PER_MINUTE', 4)),
            'per_hour' => max(1, (int) env('PYTHON_WEB_GUEST_PER_HOUR', 20)),
        ],

        'user' => [
            'per_minute' => max(1, (int) env('PYTHON_WEB_USER_PER_MINUTE', 10)),
            'per_hour' => max(1, (int) env('PYTHON_WEB_USER_PER_HOUR', 60)),
        ],

        // Only for tests: web requests are told apart from console runs by app()->runningInConsole(), which is true
        // under PHPUnit, so the guard is off there unless a test switches this on.
        'enforce_in_console' => false,
    ],
];
