#!/bin/bash
# File: /usr/local/bin/activate-node2-master.sh

LOGFILE="/var/log/keepalived/promote-node2.log"
DB_USER="root"
DB_PASS=""

echo "$(date) ====> Promoting Node2 to MASTER" >> $LOGFILE

mysql -u$DB_USER -p$DB_PASS -e "
STOP SLAVE;
RESET SLAVE ALL;
SET GLOBAL read_only = OFF;
" >> $LOGFILE 2>&1

echo "$(date) ====> Node2 is now MASTER" >> $LOGFILE
