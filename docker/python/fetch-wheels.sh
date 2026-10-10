#!/bin/sh
# Downloads the two vnstock wheels into docker/python/wheelhouse/ (git-ignored) so the Docker image can be rebuilt without depending on the
# vnstock package index being up or still offering these versions (it keeps only the two newest). Run it once on a fresh clone, from the
# project root:   sh docker/python/fetch-wheels.sh
# Only these two exact names and versions are asked of the extra index (--no-deps): everything else comes from PyPI at build time.
# Licences: vnstock = "Custom (Personal Use)", vnai = proprietary: keep the wheels local, never commit or redistribute them.
set -e
VNSTOCK=4.0.9
VNAI=2.6.3
cd "$(dirname "$0")/wheelhouse"
docker run --rm -v "$(pwd -W 2>/dev/null || pwd):/w" python:3.13-slim \
  pip download --no-deps -d /w --extra-index-url https://vnstocks.com/api/simple "vnstock==$VNSTOCK" "vnai==$VNAI"
ls -la
