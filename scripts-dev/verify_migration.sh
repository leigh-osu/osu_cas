#!/bin/bash
#
# Post-rebuild migration verification wrapper.
#
# Runs verify_migration.php inside the DDEV web container via drush. The PHP
# script prints a final "VERIFICATION: PASS" or "VERIFICATION: FAIL" line
# (it deliberately doesn't call exit() because `drush php:script` traps exit
# codes). We grep that line to set our exit status: 0 = all checks passed,
# 1 = at least one check failed.
#
# Usage:
#   bash scripts-dev/verify_migration.sh

set -u

# Path inside the web container — DDEV mounts the project at /var/www/html, so
# drush's cwd (docroot) sees the script at ../scripts-dev/.
output=$(ddev drush scr ../scripts-dev/verify_migration.php 2>&1)
echo "$output"

if echo "$output" | grep -q '^VERIFICATION: PASS$'; then
  exit 0
fi
if echo "$output" | grep -q '^VERIFICATION: FAIL$'; then
  exit 1
fi
# Neither line found — the script crashed before printing its verdict.
echo "VERIFICATION: ERROR (no verdict line — see output above)" >&2
exit 2
