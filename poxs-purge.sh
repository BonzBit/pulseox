#!/bin/bash
# Keep about 7 days of pulseox samples and reclaim disk space.
set -euo pipefail

DB=/var/www/html/pulseox.db
KEEP_ROWS=$((7 * 24 * 60 * 60)) # ~1 sample/sec
LOG_TAG=poxs-purge

if [[ ! -f "$DB" ]]; then
    logger -t "$LOG_TAG" "database missing: $DB"
    exit 1
fi

before=$(stat -c%s "$DB")
logger -t "$LOG_TAG" "starting; size=${before} keep_rows=${KEEP_ROWS}"

restore_services() {
    systemctl start poxs.service || true
    systemctl start poxs-watchdog.timer || true
}
trap restore_services EXIT

systemctl stop poxs-watchdog.timer
systemctl stop poxs.service

sqlite3 "$DB" "DELETE FROM pulseox WHERE id < (SELECT IFNULL(MAX(id), 0) FROM pulseox) - ${KEEP_ROWS}; VACUUM;"

after=$(stat -c%s "$DB")
logger -t "$LOG_TAG" "finished; size=${after} (was ${before})"
