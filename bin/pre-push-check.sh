#!/usr/bin/env bash
# pre-push-check.sh
#
# Run all local quality gates in the correct order before pushing or opening a PR.
# Both checks must pass — fix any failures before pushing.
#
# Usage:
#   ./bin/pre-push-check.sh
#
# Requirements:
#   - Docker container wp-tests-db must be running  (docker start wp-tests-db)
#   - WordPress test environment installed           (./bin/setup-test-env.sh)

set -e

PASS="\033[0;32m✔\033[0m"
FAIL="\033[0;31m✘\033[0m"
BASE_BRANCH=${1:-origin/develop}

echo ""
echo "=== Pre-push checks (base: $BASE_BRANCH) ==="
echo ""

# ── 1. PHP coding standards ────────────────────────────────────────────────────
echo "→ Running phpcs-changed against $BASE_BRANCH..."
CHANGED=$(git diff "$(git merge-base HEAD "$BASE_BRANCH")" --relative --name-only -- '*.php')

if [ -z "$CHANGED" ]; then
	echo -e "  $PASS No PHP files changed — skipping phpcs."
else
	if composer exec phpcs-changed -- -s --git --git-base "$BASE_BRANCH" $CHANGED; then
		echo -e "  $PASS phpcs-changed passed."
	else
		echo -e "  $FAIL phpcs-changed failed. Fix the violations above before pushing."
		exit 1
	fi
fi

echo ""

# ── 2. PHPUnit tests ───────────────────────────────────────────────────────────
echo "→ Running PHPUnit..."
if vendor/bin/phpunit; then
	echo -e "  $PASS All tests passed."
else
	echo -e "  $FAIL Tests failed. Fix the failures above before pushing."
	exit 1
fi

echo ""
echo -e "=== $PASS All checks passed. Safe to push. ==="
echo ""
