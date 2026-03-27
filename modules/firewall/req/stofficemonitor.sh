#!/bin/bash

PING_IP="172.16.90.50"         # IP to monitor
FAILED_TIME=0                  # Seconds failed
THRESHOLD=10                   # 1 minute
REBOOT_SCRIPT="/opt/cp-failover/stoffice.sh"   # your reboot script

while true; do

    if ping -c1 -W1 $PING_IP > /dev/null 2>&1; then
        echo "Host is up"
        FAILED_TIME=0
    else
        echo "Ping failed..."
        FAILED_TIME=$((FAILED_TIME + 1))
    fi

    # If failed 60 consecutive times (1 per second → 60 sec = 1 minute)
    if [ $FAILED_TIME -ge $THRESHOLD ]; then
        echo "DOWN for 1 minute → triggering failover!"
        $REBOOT_SCRIPT
        FAILED_TIME=0
    fi

    sleep 1
done

