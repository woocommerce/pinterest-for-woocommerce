#!/bin/bash

# Lint branch
#
# Compare committed PHP changes with the merge base of the supplied base ref.
# Existing findings are ignored; new errors and warnings fail at severity 5.
#
# Example:
# ./lint-branch.sh base-branch

set -eu

ROOTDIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOTDIR"
base_ref=${1:-origin/develop}
base_commit=$(git merge-base HEAD "$base_ref")
changed_files=$(mktemp)
trap 'rm -f "$changed_files"' EXIT

git diff --name-only --diff-filter=ACMR -z "$base_commit" HEAD -- '*.php' > "$changed_files"
files=()
while IFS= read -r -d '' file; do
	files+=("$file")
done < "$changed_files"

if [ "${#files[@]}" -eq 0 ]; then
	echo 'No committed PHP changes to check.'
	exit 0
fi

php vendor/bin/phpcs-changed -s --error-severity=5 --warning-severity=5 --git --git-base "$base_commit" "${files[@]}"
