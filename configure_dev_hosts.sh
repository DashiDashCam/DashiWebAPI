#!/usr/bin/env bash

# Define constants
ONE=1
DNS_FRONTEND="192.168.33.105  dashidashcam.com"
DNS_API="192.168.33.105  api.dashidashcam.com"

# Require script to be run as root
if [[ $(/usr/bin/id -u) -ne 0 ]]; then
    echo "Not running as root"
    exit
fi

# Add DNS record for frontend if one does not exist
grep -Fxq "$DNS_FRONTEND" /etc/hosts
if [ $? -eq $ONE ]; then
    echo "DNS record for Frontend missing, adding"
    echo "$DNS_FRONTEND" >> /etc/hosts
fi

# Add DNS record for api if one does not exist
grep -Fxq "$DNS_API" /etc/hosts
if [ $? -eq $ONE ]; then
    echo "DNS record for API missing, adding"
    echo "$DNS_API" >> /etc/hosts
fi
