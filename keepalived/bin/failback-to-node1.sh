#!/bin/bash
# File: /etc/keepalived/bin/failback-to-node1.sh

LOGFILE="/var/log/keepalived/failback-node1.log"
DB_USER="root"
DB_PASS=""
SLAVE_HOST="node2"
REPL_USER="repl"
REPL_PASS="repl_password"

echo "$(date) ====> Starting Failback to Node1" >> $LOGFILE

# 1. Rsync from Node2 to Node1 (sync uploads)
echo "$(date) - Syncing files from Node2..." >> $LOGFILE
rsync -az --delete root@$SLAVE_HOST:/var/www/html/uploads/ /var/www/html/uploads/ >> $LOGFILE 2>&1

# 2. Import new database data (from Node2)
echo "$(date) - Dumping Node2 DB..." >> $LOGFILE
ssh root@$SLAVE_HOST "mysqldump -u$DB_USER ha_app" > /tmp/ha_app_from_slave.sql

echo "$(date) - Restoring dump to Node1..." >> $LOGFILE
mysql -u$DB_USER ha_app < /tmp/ha_app_from_slave.sql

# 3. Set Node1 as new MASTER
echo "$(date) - Resetting MASTER on Node1..." >> $LOGFILE
mysql -u$DB_USER -e "RESET MASTER;" >> $LOGFILE 2>&1

# 4. Get MASTER coordinates
MASTER_FILE=$(mysql -u$DB_USER -e "SHOW MASTER STATUS\G" | grep File | awk '{print $2}')
MASTER_POS=$(mysql -u$DB_USER -e "SHOW MASTER STATUS\G" | grep Position | awk '{print $2}')
echo "$(date) - Master File: $MASTER_FILE, Position: $MASTER_POS" >> $LOGFILE

# 5. Configure Node2 as SLAVE
echo "$(date) - Configuring Node2 as SLAVE..." >> $LOGFILE
ssh root@$SLAVE_HOST "mysql -u$DB_USER -e \"
STOP SLAVE;
RESET SLAVE ALL;
CHANGE MASTER TO \\
    MASTER_HOST='192.168.137.101', \\
    MASTER_USER='$REPL_USER', \\
    MASTER_PASSWORD='$REPL_PASS', \\
    MASTER_LOG_FILE='$MASTER_FILE', \\
    MASTER_LOG_POS=$MASTER_POS;
START SLAVE;
SET GLOBAL read_only = ON;
\"" >> $LOGFILE 2>&1

echo "$(date) ====> FAILBACK to Node1 complete." >> $LOGFILE
