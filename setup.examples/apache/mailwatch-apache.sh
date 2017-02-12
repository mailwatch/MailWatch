#!/bin/bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
WebFolder="$1"

if ( type "apache2" > /dev/null 2>&1 ); then
  apacheBin="apache2"
else
  apacheBin="httpd"
fi
if [ -z $("$apacheBin" -v | grep "Apache/2.4") ]
    sed -i -e "s/ALLGRANTED/Require all granted/" "$DIR/etc/apache2/conf-available/mailwatch.conf"
  else
    sed -i -e "s/ALLGRANTED/Order allow,deny\n  Allow from all/" "$DIR/etc/apache2/conf-available/mailwatch.conf"
fi
# a2enconf mailwatch.conf
ln -r -s /etc/apache2/conf-enabled/mailwatch.conf /etc/apache2/conf-available/mailwatch.conf

sed -i -e "s/WEBFOLDER/$WebFolder/" "$DIR/etc/apache2/conf-enabled/mailwatch.conf"
cp "$DIR/etc/apache2/conf-enabled/mailwatch.conf" /etc/apache2/conf-enabled/mailwatch.conf

a2enmod ssl
/etc/init.d/apache2 reload
