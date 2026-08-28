#!/bin/bash
# Import the pushed database on Acquia stage.
# Uses sql:connect piped straight into mysql: streaming 1.4GB through
# drush sql:cli's stdin is slow and the process did not survive it.
cd /var/www/html/osucas.stage/docroot || exit 1
DRUSH=/var/www/html/osucas.stage/vendor/bin/drush
URI=osucasstage.prod.acquia-sites.com
DUMP=/mnt/files/osucas.stage/files-private/osucas_push.sql.gz

echo "=== dropping existing tables ==="
"$DRUSH" --uri="$URI" sql:drop -y || exit 1

CONN=$("$DRUSH" --uri="$URI" sql:connect)
echo "=== importing via: ${CONN%% *} ==="
gunzip -c "$DUMP" | eval "$CONN"
echo "import exit: $?"

echo "=== post-import counts ==="
"$DRUSH" --uri="$URI" sql:query "SELECT COUNT(*) AS tables_ FROM information_schema.tables WHERE table_schema=DATABASE()"
"$DRUSH" --uri="$URI" sql:query "SELECT COUNT(*) AS nodes FROM node_field_data"
"$DRUSH" --uri="$URI" sql:query "SELECT COUNT(*) AS files FROM file_managed"
echo "=== done ==="
