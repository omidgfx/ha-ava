#!/bin/bash

LOGFILE="/var/log/keepalived/check_inotify.log"
RETRIES=3

for i in $(seq 1 $RETRIES); do
    pgrep -f inotifywait > /dev/null && exit 0

    echo "$(date): inotifywait not running, trying to fix it..." >> $LOGFILE

    if timeout 15 systemctl restart sync-realtime.service; then
        sleep 2
        pgrep -f inotifywait > /dev/null && {
            echo "$(date): Fix successful" >> $LOGFILE
            exit 0
        }
        echo "$(date): Fix attempt failed, will try again" >> $LOGFILE
    else
        echo "$(date): Fix command timeout or failed, will try again" >> $LOGFILE
    fi
done

echo "$(date): inotifywait FAILED after $RETRIES attempts" >> $LOGFILE
/etc/keepalived/bin/alert.sh "inotifywait FAILED after $RETRIES attempts on $(hostname)"
exit 1
