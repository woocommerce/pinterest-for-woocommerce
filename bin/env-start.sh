#!/usr/bin/env bash

# Starts the wp-env tests site (.wp-env.test.json). The development site is `npm run env:start`.
# Extra arguments go to `wp-env start`.
set -euo pipefail

cd "$(dirname "$0")/.."

npm run wp-env:test -- start "$@"
