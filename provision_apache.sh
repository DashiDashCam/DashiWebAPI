#!/usr/bin/env bash

# Apache web root path
API_WEB_ROOT="/var/www/api.dashidashcam.com"
ANG_WEB_ROOT="/var/www/dashidashcam.com"
DEFAULT_SITE_PATH="/etc/apache2/sites-enabled/000-default.conf"

# Path to code (will by symlinked into web root)
SHARE_ROOT=$1

# Password for database server (root)
PASSWORD=$(<$2)

# Mode for server configuration
MODE=$3

# Constants defining the different modes
DEV_MODE="DEV"
PRODUCTION_MODE="PRODUCTION"

# Password for api database user (accept commandline param or default to password for devmode)
if [ $MODE = $DEV_MODE ]; then
    API_USER_PASSWORD="$PASSWORD"
else
    API_USER_PASSWORD=$(<$4)
fi

# Switch to root
sudo su

export DEBIAN_FRONTEND=noninteractive

apt-get -y update

################################################## INSTALL SOFTWARE ##################################################

# Install Nginx + PHP 7
apt-get install -y nginx php-fpm

# Install MySQL + dependencies
debconf-set-selections <<< "mysql-server mysql-server/root_password password $PASSWORD"
debconf-set-selections <<< "mysql-server mysql-server/root_password_again password $PASSWORD"
apt-get -y install mysql-server php-mysql

# Install phpMyAdmin if in dev mode
if [ $MODE = $DEV_MODE ]; then
    debconf-set-selections <<< "phpmyadmin phpmyadmin/dbconfig-install boolean true"
    debconf-set-selections <<< "phpmyadmin phpmyadmin/app-password-confirm password $PASSWORD"
    debconf-set-selections <<< "phpmyadmin phpmyadmin/mysql/admin-pass password $PASSWORD"
    debconf-set-selections <<< "phpmyadmin phpmyadmin/mysql/app-pass password $PASSWORD"
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
#git clone https://github.com/ $ANG_WEB_ROOT

# Download backend code (needed since not "shared" through vagrant in production)
if [ $MODE = $PRODUCTION_MODE ]; then
    # API Code
    git clone git@github.com:DashiDashCam/DashiWebAPI.git $API_WEB_ROOT

# Otherwise link the shared folder to supply code
else
    # Link API root to Apache2's expected path (if needed)
    if ! [ -L $API_WEB_ROOT ]; then
      rm -rf $API_WEB_ROOT
      ln -fs $SHARE_ROOT $API_WEB_ROOT
    fi
fi

# Run composer (download dependencies)
composer --working-dir=$API_WEB_ROOT install

################################################## APACHE CONFIG ##################################################

# Destroy default site (if needed)
if [ -L $DEFAULT_SITE_PATH ]; then
    rm -f $DEFAULT_SITE_PATH
fi

# Create virtual server configs (link if dev copy if production)
if [ $MODE = $DEV_MODE ]; then
    ln -fs $API_WEB_ROOT/api.conf /etc/apache2/sites-enabled/api.dashidashcam.com.conf
    #ln -fs $API_WEB_ROOT/insecurity.conf /etc/apache2/sites-enabled/dashidashcam.com.conf
else
    cp $API_WEB_ROOT/api.conf /etc/apache2/sites-enabled/api.dashidashcam.com.conf
    #cp $API_WEB_ROOT/insecurity.conf /etc/apache2/sites-enabled/dashidashcam.com.conf
fi

# Configure web root permissions if in dev mode
if [ $MODE = $DEV_MODE ]
then
    adduser ubuntu www-data
    chown -R www-data:www-data /var/www
    chmod -R g+rw /var/www
fi

# Enable Apache mod_rewrite
a2enmod rewrite

################################################## RESTART SERVICES ##################################################

# Restart Apache2
service apache2 restart

################################################## MYSQL CONFIG ##################################################


if [ $MODE = $PRODUCTION_MODE ]; then
    # Remove debug signal
    rm "$API_WEB_ROOT/debug_mode"

    # Modifies the passwords in source code of core.php and scanner.py
    bash $API_WEB_ROOT/cred_config.sh $API_USER_PASSWORD
fi

# Use tmp file to securely supply mysql password
OPTFILE="$(mktemp -q --tmpdir "${inname}.XXXXXX")"
trap 'rm -f "$OPTFILE"' EXIT
chmod 0600 "$OPTFILE"
cat >"$OPTFILE" <<EOF
[client]
password="${PASSWORD}"
EOF

# Build the database schema
mysql --defaults-extra-file="$OPTFILE" --user="root" < "$API_WEB_ROOT/schema.sql"

# Create API user in db
mysql --defaults-extra-file="$OPTFILE" --user="root" Dashi <<EOF
CREATE USER 'api'@'localhost' IDENTIFIED BY '$API_USER_PASSWORD';
GRANT SELECT ON Dashi.* TO 'api'@'localhost';
GRANT UPDATE ON Dashi.* TO 'api'@'localhost';
GRANT INSERT ON Dashi.* TO 'api'@'localhost';
GRANT DELETE ON Dashi.* TO 'api'@'localhost';
EOF
