#!/bin/bash

TYPE=$1
NAME=$2
STATE=$3

LOGFILE="/var/log/keepalived_failover.log"
MESSAGE="Keepalived notification on $NAME: State changed to $STATE (Type: $TYPE)"

echo "$(date '+%Y-%m-%d %H:%M:%S') - $MESSAGE" >> "$LOGFILE"

/etc/keepalived/bin/alert.sh "$MESSAGE"

if [ "$STATE" = "MASTER" ]; then
    /etc/keepalived/bin/failback-to-node1.sh >> /var/log/keepalived/failover.log 2>&1 &
fi
