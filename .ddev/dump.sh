set -e
mysqldump --no-tablespaces --single-transaction --quick --default-character-set=utf8mb4 -h db -u db -pdb db 2>/dev/null | gzip -1 > /tmp/osucas_push.sql.gz
ls -lh /tmp/osucas_push.sql.gz
