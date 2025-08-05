#!/bin/bash
LOGFILE=/var/log/rsync_realtime.log

echo "$(date): Starting rsync realtime sync" >> $LOGFILE 2>&1

# Initial sync once at start
rsync -az --delete --chown=www-data:www-data /var/www/html/ root@node2:/var/www/html/ >> $LOGFILE 2>&1

inotifywait -m -r /var/www/html -e modify,create,delete,move --format '%w%f %e' | while read FILE EVENT
do
    echo "$(date): Detected $EVENT on $FILE" >> $LOGFILE 2>&1
    rsync -az --delete --chown=www-data:www-data /var/www/html/ root@node2:/var/www/html/ >> $LOGFILE 2>&1
done