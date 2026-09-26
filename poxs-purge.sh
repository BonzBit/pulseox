#!/bin/bash
# Back up pulseox.db, keep about 7 days of samples, and reclaim disk space.
set -euo pipefail

DB=/var/www/html/pulseox.db
BACKUP_DIR=/mnt/boz/pulseox
KEEP_ROWS=$((7 * 24 * 60 * 60)) # ~1 sample/sec
LOG_TAG=poxs-purge

if [[ ! -f "$DB" ]]; then
    logger -t "$LOG_TAG" "database missing: $DB"
    exit 1
fi

if [[ ! -d "$BACKUP_DIR" ]]; then
    logger -t "$LOG_TAG" "backup dir missing: $BACKUP_DIR"
    exit 1
fi

before=$(stat -c%s "$DB")
stamp=$(date +%Y%m%d-%H%M%S)
backup="$BACKUP_DIR/pulseox-${stamp}.db"
logger -t "$LOG_TAG" "starting; size=${before} keep_rows=${KEEP_ROWS} backup=${backup}"

restore_services() {
    systemctl start poxs.service || true
    systemctl start poxs-watchdog.timer || true
}
trap restore_services EXIT

systemctl stop poxs-watchdog.timer
systemctl stop poxs.service

sqlite3 "$DB" ".backup ${backup}"
if [[ ! -s "$backup" ]]; then
    logger -t "$LOG_TAG" "backup failed or empty: ${backup}"
    exit 1
fi
logger -t "$LOG_TAG" "backup complete; size=$(stat -c%s "$backup")"

sqlite3 "$DB" "DELETE FROM pulseox WHERE id < (SELECT IFNULL(MAX(id), 0) FROM pulseox) - ${KEEP_ROWS}; VACUUM;"

after=$(stat -c%s "$DB")
logger -t "$LOG_TAG" "finished; size=${after} (was ${before})"
