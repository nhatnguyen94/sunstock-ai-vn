#!/usr/bin/env python3
"""Test fixture for tests/Feature/Support/PythonRunnerTest.php: prints the vnai agent-bootstrap switches it was started with."""
import json
import os

print(json.dumps({
    "disable": os.environ.get("VNSTOCK_DISABLE_AGENT_SETUP"),
    "targets": os.environ.get("VNSTOCK_AGENT_TARGETS"),
}))
