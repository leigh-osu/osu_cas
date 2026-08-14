#!/bin/bash
# Detaches the import so the ssh session can close.
P=/mnt/files/osucas.stage/files-private
nohup bash "$P/stage_import.sh" > "$P/import.log" 2>&1 &
echo "started pid $!"
