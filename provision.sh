#!/usr/bin/env bash

# Constants defining the different modes
DEV_MODE="DEV"
PRODUCTION_MODE="PRODUCTION"
DEFAULT_SITE_PATH="/etc/nginx/sites-enabled/default"

# Load config file
. $1

# Ensure required variables exist and are valid (NOTE: FRONT_END_REPO is optional, no check required)
if [ -z ${MODE+x} ]; then echo "MODE is unset in $1, exiting"; exit 1; fi

if [ "$MODE" -ne "$DEV_MODE" ] && [ "$MODE" -ne "$PRODUCTION_MODE" ]; then
    echo "MODE must be either \"DEV\" or \"PRODUCTION\""
fi

if [ -z ${DOMAIN+x} ]; then echo "DOMAIN is unset in $1, exiting"; exit 1; fi
if [ -z ${BACK_END_REPO+x} ]; then echo "BACK_END_REPO is unset in $1, exiting"; exit 1; fi
if [ -z ${DB_PASSWORD+x} ]; then echo "DB_PASSWORD is unset in $1, exiting"; exit 1; fi
if [ -z ${SHARE_ROOT+x} ]; then echo "SHARE_ROOT is unset in $1, exiting"; exit 1; fi

# Nginx web root path
API_WEB_ROOT="/var/www/api.$DOMAIN"
FRONT_END_WEB_ROOT="/var/www/$DOMAIN"

# Switch to root
sudo su

export DEBIAN_FRONTEND=noninteractive

apt-get -y update

################################################## INSTALL SOFTWARE ##################################################

# Install Nginx + PHP 7
apt-get install -y nginx php-fpm

# Install MySQL + dependencies
debconf-set-selections <<< "mysql-server mysql-server/root_password password $DB_PASSWORD"
debconf-set-selections <<< "mysql-server mysql-server/root_password_again password $DB_PASSWORD"
apt-get -y install mysql-server php-mysql

# Install phpMyAdmin if in dev mode
if [ $MODE = $DEV_MODE ]; then
    debconf-set-selections <<< "phpmyadmin phpmyadmin/dbconfig-install boolean true"
    debconf-set-selections <<< "phpmyadmin phpmyadmin/app-password-confirm password $DB_PASSWORD"
    debconf-set-selections <<< "phpmyadmin phpmyadmin/mysql/admin-pass password $DB_PASSWORD"
    debconf-set-selections <<< "phpmyadmin phpmyadmin/mysql/app-pass password $DB_PASSWORD"
    debconf-set-selections <<< "phpmyadmin phpmyadmin/reconfigure-webserver multiselect apache2"
    apt-get install -y phpmyadmin
fi

# Install composer dependencies
apt-get -y install git zip unzip php7.0-zip

# Allow for installation of composer
chown -R `whoami`:root /usr/local/bin
chown -R `whoami`:root /usr/local/share

# Install Composer
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

################################################## DOWNLOAD CODE ##################################################

# Download frontend code
if ! [ -z ${FRONT_END_REPO+x} ]; then
    git clone $FRONT_END_REPO $FRONT_END_WEB_ROOT
fi

# Download backend code (needed since not "shared" through vagrant in production)
if [ $MODE = $PRODUCTION_MODE ]; then
    # API Code
    git clone $BACK_END_REPO $API_WEB_ROOT

# Otherwise link the shared folder to supply code
else
    # Link API root to Nginx's expected path (if needed)
    if ! [ -L $API_WEB_ROOT ]; then
      rm -rf $API_WEB_ROOT
      ln -fs $SHARE_ROOT $API_WEB_ROOT
    fi
fi

# Run composer (download dependencies)
composer --working-dir=$API_WEB_ROOT install

################################################## NGINX CONFIG ##################################################

# Destroy default site (if needed)
if [ -L $DEFAULT_SITE_PATH ]; then
    rm -f $DEFAULT_SITE_PATH
fi

# Create virtual server configs (link if dev copy if production)
if [ $MODE = $DEV_MODE ]; then
    ln -fs "$API_WEB_ROOT/api.$DOMAIN" "/etc/nginx/sites-available/api.$DOMAIN"
    if ! [ -z ${FRONT_END_REPO+x} ]; then
        ln -fs "$FRONT_END_WEB_ROOT/$DOMAIN" "/etc/nginx/sites-available/$DOMAIN"
    fi
else
    cp "$API_WEB_ROOT/api.$DOMAIN" "/etc/nginx/sites-available/api.$DOMAIN"
    if ! [ -z ${FRONT_END_REPO+x} ]; then
        cp "$FRONT_END_WEB_ROOT/$DOMAIN" "/etc/nginx/sites-available/$DOMAIN"
    fi
fi

ln -fs "/etc/nginx/sites-available/api.$DOMAIN" "/etc/nginx/sites-enabled/api.$DOMAIN"
if ! [ -z ${FRONT_END_REPO+x} ]; then
    ln -fs "/etc/nginx/sites-available/$DOMAIN" "/etc/nginx/sites-enabled/$DOMAIN"
fi

# Configure web root permissions if in dev mode
if [ $MODE = $DEV_MODE ]
then
    adduser ubuntu www-data
    chown -R www-data:www-data /var/www
    chmod -R g+rw /var/www
fi

################################################## RESTART SERVICES ##################################################

# Restart Nginx
service nginx restart

################################################## MYSQL CONFIG ##################################################


if [ $MODE = $PRODUCTION_MODE ]; then
    # Remove debug signal
    if [ -L "$API_WEB_ROOT/debug_mode" ]; then
        rm "$API_WEB_ROOT/debug_mode"
    fi

    # Modifies the passwords in source code according to custom script (including sql file)
    if [ -L "$API_WEB_ROOT/cred_config.sh" ]; then
        bash $API_WEB_ROOT/cred_config.sh
    fi
fi

# Use tmp file to securely supply mysql password
OPTFILE="$(mktemp -q --tmpdir "${inname}.XXXXXX")"
trap 'rm -f "$OPTFILE"' EXIT
chmod 0600 "$OPTFILE"
cat >"$OPTFILE" <<EOF
[client]
password="${DB_PASSWORD}"
EOF

# Build the database schema
mysql --defaults-extra-file="$OPTFILE" --user="root" < "$API_WEB_ROOT/schema.sql"
