#!/bin/bash
#
# Stage D7's unmanaged public files into the D10 files tree and write the
# lists that scripts-dev/register_unmanaged_files.php consumes.
#
# D7 accumulated files uploaded outside Drupal (IMCE, FTP, direct copies) that
# have no {file_managed} row. Nothing in the migration copies them, yet they
# are linked directly from body HTML, so without this step those links 404 in
# every D10 environment. The set is ~24k files / ~16 GB.
#
# Writes:
#   scripts-dev/unmanaged/all.txt         every unmanaged file, path relative
#                                         to the public files root
#   scripts-dev/unmanaged/referenced.txt  the subset referenced from D10
#                                         content (media is created for these)
#
# Usage: bash scripts-dev/stage_unmanaged_files.sh
# Then:  ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/register_unmanaged_files.php

set -euo pipefail
cd "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

D7_FILES="${D7_FILES:-$HOME/Sites/osu/agscid7/docroot/sites/default/files}"
D10_FILES="docroot/sites/agsci.oregonstate.edu/files"
OUT="scripts-dev/unmanaged"

if [ ! -d "$D7_FILES" ]; then
  echo "D7 files directory not found: $D7_FILES (set D7_FILES=...)" >&2
  exit 1
fi
mkdir -p "$OUT"

echo "== listing D7 managed URIs"
ddev drush --uri=https://osu-cas.ddev.site sql:query \
  "SELECT uri FROM file_managed WHERE uri LIKE 'public://%'" \
  --database=migrate > "$OUT/managed_uris.txt"

echo "== listing files on disk"
( cd "$D7_FILES" && find . -type f \
    -not -path "./styles/*" -not -path "./css/*" -not -path "./js/*" \
    -not -path "./ctools/*" -not -path "./php/*" -not -path "./languages/*" \
    -not -path "./advagg_*" -not -name ".htaccess" \
  | sed 's|^\./||' | sort ) > "$OUT/disk_files.txt"

echo "== computing the unmanaged set"
python3 - "$OUT" <<'PY'
import os, sys
out = sys.argv[1]
managed = {l.strip()[len('public://'):] for l in open(out + '/managed_uris.txt')
           if l.strip().startswith('public://')}
disk = [l.rstrip('\n') for l in open(out + '/disk_files.txt') if l.strip()]
JUNK = {'.lthmb', '.css', '.js', '.tmp', '.ds_store', '.db'}
keep = [f for f in disk
        if f not in managed and os.path.splitext(f)[1].lower() not in JUNK]
open(out + '/all.txt', 'w').write('\n'.join(keep) + '\n')
print(f'  {len(disk)} on disk, {len(managed)} managed, {len(keep)} unmanaged')
PY

echo "== copying the unmanaged files into $D10_FILES"
rsync -a --ignore-existing --files-from="$OUT/all.txt" "$D7_FILES/" "$D10_FILES/"

if [ ! -s "$OUT/referenced.txt" ]; then
  echo "== NOTE: $OUT/referenced.txt is empty."
  echo "   Media is created only for files referenced from D10 content; without"
  echo "   that list, register_unmanaged_files.php registers file entities only."
  echo "   Regenerate it from a link audit, or run the register script with"
  echo "   -- --all-media to give every file a media entity."
  : > "$OUT/referenced.txt"
fi

echo "== done: $(wc -l < "$OUT/all.txt") files staged"
