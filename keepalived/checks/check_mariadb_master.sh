#!/bin/bash

LOGFILE="/var/log/keepalived/check_mariadb_master.log"
RETRIES=3
CMD="SHOW SLAVE STATUS\G" # without result, means it is master
ALERT_SCRIPT="/etc/keepalived/bin/alert.sh"

for i in $(seq 1 $RETRIES); do
    timeout 5 mysql -u root -e "$CMD" 2>>$LOGFILE | grep -q "Slave_IO_State"
    if [ $? -ne 0 ]; then
        exit 0
    fi

    echo "$(date): MariaDB is SLAVE or error on attempt $i" >> $LOGFILE

    if [ -x "$ALERT_SCRIPT" ]; then
        $ALERT_SCRIPT "MariaDB is not master on attempt $i" >> $LOGFILE 2>&1
    fi

    sleep 2
done

echo "$(date): MariaDB is not MASTER after $RETRIES attempts" >> $LOGFILE
/etc/keepalived/bin/alert.sh "MariaDB is not MASTER after $RETRIES attempts on $(hostname)"
exit 1
