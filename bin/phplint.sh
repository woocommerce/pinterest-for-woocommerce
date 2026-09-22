#!/usr/bin/env bash
set -euo pipefail

ROOTDIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOTDIR"
php vendor/bin/parallel-lint --no-colors --short --exclude vendor --exclude node_modules --exclude build .
