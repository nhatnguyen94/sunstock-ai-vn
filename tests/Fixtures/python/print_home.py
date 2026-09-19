#!/usr/bin/env python3
"""
Test fixture for tests/Feature/Support/PythonRunnerTest.php — NOT a real data script.

Prints the home directory this process sees (as JSON), which is exactly what vnstock
uses to decide where to write `~/.vnstock`. Used to prove PythonRunner points HOME at a
writable directory when the calling user's own home is not writable.
"""
import json
import os

print(json.dumps({"home": os.path.expanduser("~")}))
