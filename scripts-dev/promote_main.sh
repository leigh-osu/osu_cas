#!/bin/bash
#
# Promote develop to main WITHOUT .ddev.
#
# .ddev is tracked on develop (local dev config is project knowledge) but
# deliberately kept off main, the deploy branch. A fast-forward push would
# carry every tracked file, so promotion instead commits develop's tree
# minus .ddev onto main: one synthetic commit per promote, parented on the
# current main tip — linear history, no force pushes. The commit message
# records the develop SHA it mirrors.
#
# Usage:  bash scripts-dev/promote_main.sh
# (push develop first; the script refuses to promote unpushed work)

set -euo pipefail
cd "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

git fetch origin --quiet

DEV_LOCAL=$(git rev-parse develop)
DEV_REMOTE=$(git rev-parse origin/develop)
if [ "$DEV_LOCAL" != "$DEV_REMOTE" ]; then
  echo "develop ($DEV_LOCAL) differs from origin/develop ($DEV_REMOTE) — push develop first." >&2
  exit 1
fi

MAIN_SHA=$(git rev-parse origin/main)

# Build develop's tree without .ddev in a throwaway index.
TMP_INDEX=$(mktemp)
trap 'rm -f "$TMP_INDEX"' EXIT
export GIT_INDEX_FILE="$TMP_INDEX"
git read-tree "${DEV_LOCAL}^{tree}"
git rm -r --cached --quiet .ddev 2>/dev/null || true
NEW_TREE=$(git write-tree)
unset GIT_INDEX_FILE

if [ "$(git rev-parse "${MAIN_SHA}^{tree}")" = "$NEW_TREE" ]; then
  echo "main already matches develop (minus .ddev) — nothing to promote."
  exit 0
fi

SHORT=$(git rev-parse --short "$DEV_LOCAL")
COMMIT=$(git commit-tree "$NEW_TREE" -p "$MAIN_SHA" -m "promote develop @ ${SHORT} (without .ddev)")
git push origin "${COMMIT}:main"
echo "main -> ${COMMIT} (develop ${SHORT} minus .ddev)"
