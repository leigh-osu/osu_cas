#!/bin/bash
P=/mnt/files/osucas.stage/files-private
for f in osucas_push.sql.gz import.log run_import.sh stage_import.sh stage_check.php stage_purge_devel.php; do
  if [ -f "$P/$f" ]; then rm -f "$P/$f" && echo "  removed $f"; fi
done
echo "--- remaining ---"
ls -A "$P"
