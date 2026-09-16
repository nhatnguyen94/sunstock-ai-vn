#!/usr/bin/env python3
"""
Test fixture for tests/Feature/Support/PythonRunnerTest.php — NOT a real
data-fetching script (see py/ for those, documented in docs/PYTHON_INTEGRATION.md).

Usage: sleep_and_print.py <seconds> [--stderr-too]
Sleeps for <seconds>, then prints a small JSON object to stdout (and, if
--stderr-too is passed, an unrelated line to stderr first — used to test
PythonRunner's suppressStderr option).
"""
import sys
import json
import time

seconds = float(sys.argv[1]) if len(sys.argv) > 1 else 0
print_stderr = '--stderr-too' in sys.argv

if print_stderr:
    print("noise on stderr", file=sys.stderr)

time.sleep(seconds)
print(json.dumps({"ok": True, "slept": seconds}))
