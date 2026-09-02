#!/bin/bash
#
# Push the local post-MMI database + files to Acquia STAGE (2026-09-01).
#
# Local state being pushed: the Sep-1 prod freeze + the full MMI migration
# (rehearsal_20260901.log, exit 0). Stage code already ships osu_migrations_mmi
# (main promoted at develop c447ce2cd9), so enabling it via the pushed
# core.extension is safe. devel/migrate_devel are absent locally -- no purge
# needed. Shield is disabled on both sides -- no exposure change.
#
# This REPLACES the stage database (osucas role, schema
# 1e3c7efccf9f492e8ed07765230b96b7). Rollback: last night's stage backup
# ~/Sites/osu/acquia_backup/backup/osucas/stage/databases/osucas_1788170177.sql.gz
#
# Run from the repo root:  bash scripts-dev/push_stage_20260901.sh
set -uo pipefail

REPO="/Users/leighr/Sites/osu/osu_cas"
REMOTE="osucas.stage@osucasstage.ssh.prod.acquia-sites.com"
SSH_OPTS=(-i "$HOME/.ssh/id_rsa" -o ServerAliveInterval=30 -o ServerAliveCountMax=10 -o TCPKeepAlive=yes)
PRIV="/mnt/files/osucas.stage/files-private"        # env-level shared mount (not web-accessible)
SITEFILES="/mnt/files/osucas.stage/sites/agsci.oregonstate.edu"
DRUSH="./vendor/bin/drush --uri=agsci.stage.oregonstate.edu"
DUMP="${REPO}/.tmp-dbpush/db.sql.gz"
LOG="${REPO}/scripts-dev/stage_push_20260901.log"

# Local truth the import must reach (captured before the dump).
WANT_NODES=45432
WANT_SUBS=38407

exec > >(tee "${LOG}") 2>&1
cd "${REPO}" || exit 1

rsync_retry() {  # rsync with the Acquia drop-resistant flags, 5 attempts
  for attempt in 1 2 3 4 5; do
    rsync "$@" && return 0
    echo "rsync attempt ${attempt} failed (exit $?); retrying in 30s" >&2
    sleep 30
  done
  return 1
}

echo "=== stage push $(date) ==="
[ -f "${DUMP}" ] || { echo "FATAL: ${DUMP} missing -- re-dump first" >&2; exit 1; }
echo "dump: $(ls -lh "${DUMP}" | awk '{print $5, $9}')"

echo "--- 1. upload dump to the SHARED mount (never /mnt/tmp: node-local) ---"
rsync_retry -az --partial --timeout=180 -e "ssh ${SSH_OPTS[*]}" \
  "${DUMP}" "${REMOTE}:${PRIV}/claude-db-push.sql.gz" || exit 1

echo "--- 2. verify transfer, start import under nohup (survives SSH drops) ---"
ssh "${SSH_OPTS[@]}" "${REMOTE}" "
  set -u
  gzip -t ${PRIV}/claude-db-push.sql.gz || { echo 'FATAL: gzip -t failed -- retransfer'; exit 1; }
  gunzip -f ${PRIV}/claude-db-push.sql.gz || exit 1
  cd /var/www/html/osucas.stage || exit 1
  rm -f ${PRIV}/claude-db-import.done
  nohup sh -c '${DRUSH} sql:query --file=${PRIV}/claude-db-push.sql \
      && echo DONE > ${PRIV}/claude-db-import.done' \
    > ${PRIV}/claude-db-import.log 2>&1 &
  echo 'import started (log: ${PRIV}/claude-db-import.log)'
" || exit 1

echo "--- 3. file deltas while the import runs (--size-only: mirror/migration reset mtimes) ---"
echo "public files:"
rsync_retry -a --size-only --partial --timeout=180 --stats \
  --exclude 'styles/' --exclude 'css/' --exclude 'js/' --exclude 'php/' \
  -e "ssh ${SSH_OPTS[*]}" \
  "${REPO}/docroot/sites/agsci.oregonstate.edu/files/" \
  "${REMOTE}:${SITEFILES}/files/" || exit 1
echo "private files:"
rsync_retry -a --size-only --partial --timeout=180 --stats \
  -e "ssh ${SSH_OPTS[*]}" \
  "${REPO}/files/agsci/private-files/" \
  "${REMOTE}:${SITEFILES}/files-private/" || exit 1

echo "--- 4. poll import completion (nodes=${WANT_NODES} AND webform_submission=${WANT_SUBS}) ---"
for i in $(seq 1 90); do   # up to 45 min
  if ssh "${SSH_OPTS[@]}" "${REMOTE}" "test -f ${PRIV}/claude-db-import.done"; then
    counts=$(ssh "${SSH_OPTS[@]}" "${REMOTE}" \
      "cd /var/www/html/osucas.stage && ${DRUSH} sql:query 'SELECT (SELECT COUNT(*) FROM node), (SELECT COUNT(*) FROM webform_submission);'" | tr -d '[:space:]')
    if [ "${counts}" = "${WANT_NODES}${WANT_SUBS}" ]; then
      echo "import complete: counts match (${WANT_NODES}/${WANT_SUBS})"
      break
    fi
    echo "FATAL: done-marker present but counts=${counts}; inspect ${PRIV}/claude-db-import.log" >&2
    exit 1
  fi
  [ "$i" = 90 ] && { echo "FATAL: import did not finish in 45 min; inspect ${PRIV}/claude-db-import.log" >&2; exit 1; }
  sleep 30
done

echo "--- 5. cache rebuild + verification ---"
ssh "${SSH_OPTS[@]}" "${REMOTE}" "cd /var/www/html/osucas.stage && ${DRUSH} cr" || exit 1
echo "agsci front: $(curl -s -o /dev/null -w '%{http_code}' https://agsci.stage.oregonstate.edu/)"
echo "mmi front:   $(curl -s -o /dev/null -w '%{http_code}' https://mmi.stage.oregonstate.edu/) (401 = Acquia-edge shield, expected)"
ssh "${SSH_OPTS[@]}" "${REMOTE}" "cd /var/www/html/osucas.stage && ${DRUSH} sql:query \"SELECT COUNT(*) FROM node WHERE nid >= 400000;\"" \
  | sed 's/^/MMI-offset nodes on stage: /'

echo "--- 6. cleanup ---"
ssh "${SSH_OPTS[@]}" "${REMOTE}" "rm -f ${PRIV}/claude-db-push.sql ${PRIV}/claude-db-import.done"
echo "kept: ${PRIV}/claude-db-import.log (server-side import log)"
echo "local dump kept at ${DUMP} -- delete once stage is verified"
echo "=== stage push finished $(date) ==="
