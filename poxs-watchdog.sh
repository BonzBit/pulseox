#!/bin/bash
# Restart poxs.service if the serial reader has not logged a sample recently.
set -euo pipefail

STAMP_FILE=/home/sam/last_update.txt
MAX_AGE_SEC=30

if [[ ! -f "$STAMP_FILE" ]]; then
    logger -t poxs-watchdog "missing $STAMP_FILE; restarting poxs.service"
    systemctl restart poxs.service
    exit 0
fi

now=$(date +%s)
last=$(tr -dc '0-9' < "$STAMP_FILE")
if [[ -z "$last" ]]; then
    logger -t poxs-watchdog "invalid $STAMP_FILE; restarting poxs.service"
    systemctl restart poxs.service
    exit 0
fi

age=$((now - last))
if (( age > MAX_AGE_SEC )); then
    logger -t poxs-watchdog "last sample ${age}s ago; restarting poxs.service"
    systemctl restart poxs.service
fi
