#!/bin/bash

LOGFILE="/var/log/keepalived/check_apache2.log"
RETRIES=3
FIX_LOGGED=0

for i in $(seq 1 $RETRIES); do
    timeout 5 systemctl is-active --quiet apache2 && exit 0

    if [ "$FIX_LOGGED" -eq 0 ]; then
        echo "$(date): apache2 is down, trying to fix it..." >> $LOGFILE
        FIX_LOGGED=1
    fi

    if timeout 15 systemctl restart apache2; then
        sleep 2
        timeout 5 systemctl is-active --quiet apache2 && {
            echo "$(date): Fix successful" >> $LOGFILE
            exit 0
        }
        echo "$(date): Fix attempt failed, will try again" >> $LOGFILE
    else
        echo "$(date): Fix command timeout or failed, will try again" >> $LOGFILE
    fi
done

echo "$(date): apache2 FAILED after $RETRIES attempts" >> $LOGFILE
/etc/keepalived/bin/alert.sh "apache2 FAILED after $RETRIES attempts on $(hostname)"
exit 1
