#!/bin/bash
#
# Cloud Hook: post-db-copy
#
# Runs whenever a database is copied between environments (Workflow page,
# acli, or the API). See ../README.md for details.
#
# Usage: post-db-copy site target-env db-name source-env
#
# Two jobs:
#
# 1. Re-enable shield on dev and stage ("test" in hook terms). Basic auth on
#    the preview environments is the Drupal shield module: the repo exports
#    core.extension with shield enabled and shield.settings with
#    shield_enable FALSE, and each site's settings.php flips it on when
#    AH_SITE_ENVIRONMENT is dev/stage and the per-environment secret is set.
#    Prod never has the module enabled, so every prod -> dev/stage copy
#    silently uninstalls it and the environment answers without credentials
#    until someone notices (2026-09-01 and 2026-09-23). A settings.php
#    override cannot install a module and nothing imports config during a
#    DB copy, so this hook is the place to put it back. Only sites whose
#    settings.php carries the shield override are touched.
#
# 2. Rebuild caches for every site, since the copied database carries the
#    source environment's cache tables and container.

site="$1"
target_env="$2"
db_name="$3"
source_env="$4"
DRUPAL_ROOT="/var/www/html/docroot"
SITES_DIR="${DRUPAL_ROOT}/sites"

drush_site() {
  local uri="$1"
  shift
  drush --no-ansi --root="${DRUPAL_ROOT}" @"${site}"."${target_env}" -l "${uri}" "$@"
}

echo "post-db-copy: ${db_name} copied from ${source_env} to ${target_env}"

for site_dir in "${SITES_DIR}"/*/; do
  uri=$(basename "${site_dir}")
  [ "${uri}" = "default" ] && continue
  [ -f "${site_dir}/settings.php" ] || continue
  echo "== ${uri}"

  case "${target_env}" in
    dev|test)
      if grep -q "shield_enable" "${SITES_DIR}/${uri}/settings.php"; then
        echo "Ensuring shield is enabled on ${uri}"
        if ! drush_site "${uri}" pm:install shield -y; then
          echo "ERROR: could not enable shield on ${uri}" >&2
        fi
      fi
      ;;
  esac

  if ! drush_site "${uri}" cr; then
    echo "ERROR: cr failed for ${uri}" >&2
  fi
done

echo "post-db-copy: done."
