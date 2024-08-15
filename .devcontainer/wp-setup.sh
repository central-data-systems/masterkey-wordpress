#!/bin/bash
set -e
#Site configuration options
SITE_TITLE="MasterKey"
ADMIN_USER=admin
ADMIN_PASS=password
ADMIN_EMAIL="admin@localhost.lan"
# Space-separated list of plugin ID's to install and activate
#PLUGINS="advanced-custom-fields"
PLUGINS=""

#Set to true to wipe out and reset your wordpress install (on next container rebuild)
WP_RESET=${1:-false}

echo "Setting up WordPress"
DEVDIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
cd /var/www/html;
if $WP_RESET ; then
    echo "Resetting WP"
    wp plugin delete $PLUGINS
    wp db reset --yes
    rm -f wp-config.php;
fi

if [ ! -f wp-config.php ]; then 
    echo "Configuring";
    wp config create --dbhost="db" --dbname="wordpress" --dbuser="wp_user" --dbpass="wp_pass" --skip-check;
    wp core install --url="http://localhost:8080" --title="$SITE_TITLE" --admin_user="$ADMIN_USER" --admin_email="$ADMIN_EMAIL" --admin_password="$ADMIN_PASS" --skip-email;
    # wp plugin install $PLUGINS --activate
    #TODO: Only activate plugin if it contains files - i.e. might be developing a theme instead
    wp plugin activate masterkey

    #Data import
    for f in $DEVDIR/data/*.sql; do
        wp db import $f
    done

    cp -r $DEVDIR/data/plugins/* /var/www/html/wp-content/plugins/
    for p in $DEVDIR/data/plugins/*; do
        wp plugin activate $(basename $p)
    done

else
    echo "Already configured"
fi