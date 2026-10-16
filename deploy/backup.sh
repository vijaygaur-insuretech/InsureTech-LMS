#!/usr/bin/env bash
# Daily backup of the LMS database and uploaded files. Keeps the last 7 days in $LMS_HOME/backups.
# Installed as a cron job for the deploy user:  30 2 * * *  bash /opt/insuretech-lms/app/deploy/backup.sh
set -euo pipefail

LMS_HOME="${LMS_HOME:-/opt/insuretech-lms}"
KEEP_DAYS="${KEEP_DAYS:-7}"
set -a; . "$LMS_HOME/.env"; set +a

dest="$LMS_HOME/backups"
stamp=$(date +%F-%H%M)
mkdir -p "$dest"; chmod 700 "$dest"

docker exec insuretech-lms-db-1 pg_dump -U "$LMS_DB_USER" -d "$LMS_DB_NAME" --no-owner | gzip > "$dest/db-$stamp.sql.gz"
docker run --rm -v insuretech-lms_lmsdata:/data:ro -v "$dest":/backup alpine:3.20 \
    tar czf "/backup/files-$stamp.tar.gz" -C /data --exclude=./cache --exclude=./localcache --exclude=./temp --exclude=./sessions .

find "$dest" -name 'db-*.sql.gz' -mtime +"$KEEP_DAYS" -delete
find "$dest" -name 'files-*.tar.gz' -mtime +"$KEEP_DAYS" -delete
echo "$(date '+%F %T') backup ok: $(du -sh "$dest" | cut -f1) in $dest"
