#!/usr/bin/env python3
"""Test fixture for tests/Feature/Support/PythonRunnerTest.php: prints the VNSTOCK_API_KEY it was started with."""
import json
import os

print(json.dumps({"key": os.environ.get("VNSTOCK_API_KEY")}))
