#!/bin/bash

LOGFILE="/var/log/keepalived/check_sync_realtime.log"
RETRIES=3

for i in $(seq 1 $RETRIES); do
    timeout 5 systemctl is-active --quiet rsync-realtime.service && exit 0

    echo "$(date): sync-realtime.service down, trying to fix it..." >> $LOGFILE

    if timeout 15 systemctl restart rsync-realtime.service; then
        sleep 2
        timeout 5 systemctl is-active --quiet rsync-realtime.service && {
            echo "$(date): Fix successful" >> $LOGFILE
            exit 0
        }
        echo "$(date): Fix attempt failed, will try again" >> $LOGFILE
    else
        echo "$(date): Fix command timeout or failed, will try again" >> $LOGFILE
    fi
done

echo "$(date): sync-realtime FAILED after $RETRIES attempts" >> $LOGFILE
/etc/keepalived/bin/alert.sh "sync-realtime.service FAILED after $RETRIES attempts on $(hostname)"
exit 1
