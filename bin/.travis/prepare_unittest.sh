#!/bin/sh

# File for setting up system for unit/integration testing

# Disable xdebug to speed things up as we don't currently generate coverge on travis
# And make sure we use UTF-8 encoding
phpenv config-rm xdebug.ini
echo "default_charset = UTF-8" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini

# Setup DB
if [ "$DB" = "mysql" ] ; then
    # https://github.com/travis-ci/travis-ci/issues/3049
    # make sure we don't run out of entropy apparently (see link above)
    sudo apt-get -y install haveged
    sudo service haveged start
    # make tmpfs and run MySQL on it for reasonable performance
    sudo mkdir /mnt/ramdisk
    sudo mount -t tmpfs -o size=1024m tmpfs /mnt/ramdisk
    sudo stop mysql
    sudo mv /var/lib/mysql /mnt/ramdisk
    sudo ln -s /mnt/ramdisk/mysql /var/lib/mysql
    sudo start mysql
    # Install test db
    mysql -e "CREATE DATABASE IF NOT EXISTS testdb DEFAULT CHARACTER SET utf8mb4 DEFAULT COLLATE utf8mb4_unicode_ci;" -uroot
fi
if [ "$DB" = "postgresql" ] ; then psql -c "CREATE DATABASE testdb;" -U postgres ; psql -c "CREATE EXTENSION pgcrypto;" -U postgres testdb ; fi

if [ "$COMPOSER_REQUIRE" != "" ] ; then composer require --no-update $COMPOSER_REQUIRE ; fi
#travis_retry composer update --no-progress --no-interaction $COMPSER_ARGS
