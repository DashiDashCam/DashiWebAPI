#!/usr/bin/env bash

API_WEB_ROOT="/var/www/api.insecurityscanner.com"
ANG_WEB_ROOT="/var/www/insecurityscanner.com"

API_NAME="api.insecurityscanner.com"
FRONTEND_NAME="insecurityscanner.com"

# API
if [ $# -eq 0 || $1 = $API_NAME ]; then
    cd $API_WEB_ROOT
    git reset --hard [HEAD]
    git pull
    rm debug_mode
    composer update
fi

# Frontend
if [ $# -eq 0 || $1 = $FRONTEND_NAME ]; then
    cd $ANG_WEB_ROOT
    git reset --hard [HEAD]
    git pull
fi
