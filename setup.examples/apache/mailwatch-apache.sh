#!/bin/bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
WebFolder="$1"
echo $WebFolder
if ( type "apache2" > /dev/null 2>&1 ); then
  apacheBin="apache2"
else
  apacheBin="httpd"
fi

echo $apache24
if [[ -z $apache24 ]]; then
    sed -i -e "/ALLGRANTED/Require all granted/" "$DIR/etc/apache2/conf-available/mailwatch.conf"
else
    sed -i -e "/ALLGRANTED/Order allow,deny\n  Allow from all/" "$DIR/etc/apache2/conf-available/mailwatch.conf"
fi
sed -i -e "s~WEBFOLDER~$WebFolder~" "$DIR/etc/apache2/conf-available/mailwatch.conf"

#backup old config
if [ -f /etc/apache2/conf-available/mailwatch.conf ]; then
    mv /etc/apache2/conf-available/mailwatch.conf /etc/apache2/conf-available/mailwatch.conf.old
fi

cp "$DIR/etc/apache2/conf-available/mailwatch.conf" /etc/apache2/conf-available/mailwatch.conf

# a2enconf mailwatch.conf
ln -f -r -s /etc/apache2/conf-enabled/mailwatch.conf /etc/apache2/conf-available/mailwatch.conf

a2enmod ssl
service apache2 reload
