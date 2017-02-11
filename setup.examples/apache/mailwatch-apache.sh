#!/bin/bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

sed -i -e "s/WEBFOLDER/$WebFolder/" "$DIR/etc/apache2/conf-enabled/mailwatch.conf"
cp "$DIR/etc/apache2/conf-enabled/mailwatch.conf" /etc/apache2/conf-enabled/mailwatch.conf

a2enmod ssl
/etc/init.d/apache2 reload