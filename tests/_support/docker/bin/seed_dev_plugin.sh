#!/usr/bin/env bash
#
# Plugin-specific part of the development site seed. Called by seed_dev.sh after the store is set up.
# Pinterest needs a real merchant account, connected in wp-admin, so there is no plugin data to seed.
#
set -euo pipefail

echo "Connect a Pinterest account from Marketing > Pinterest to try the plugin."
