#!/bin/bash

set -e

echo "Stopping and disabling all encoder@ services..."

for svc in $(systemctl list-units --all --no-legend "encoder@*" | awk '{print $1}'); do
    echo "Stopping $svc"
    systemctl stop "$svc"

    echo "Disabling $svc"
    systemctl disable "$svc"
done

echo "All encoder@ services stopped and disabled."
