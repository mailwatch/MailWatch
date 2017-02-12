#!/bin/bash
# Script to apply adjustments for exim
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
Webuser="$1"

/etc/init.d/exim4 stop

cp -f "$DIR/etc/default/exim4" /etc/default/exim4
cp -R "$DIR"/etc/exim/* /etc/exim4/
cp "$DIR/etc/MailScanner/conf.d/mailwatch.conf" /etc/MailScanner/conf.d/mailwatch.conf

usermod -a -G Debian-exim clamav
usermod -a -G mtagroup clamav
usermod -a -G mtagroup Debian-exim
usermod -a -G mtagroup mail
usermod -a -G mtagroup "$Webuser"

chown -R root:root /etc/exim4
chmod -R 644 /etc/exim4
chmod 755 /etc/exim4/conf.d/
chmod 755 /etc/exim4/eximconfig/
chown root:Debian-exim /etc/exim4/passwd.client
chmod 640 /etc/exim4/passwd.client

chown Debian-exim:adm /var/log/exim4/
chmod 750 /var/log/exim4/
chown Debian-exim:adm /var/log/exim4_outgoing/
chmod 750 /var/log/exim4_outgoing/

chown Debian-exim:Debian-exim /var/spool/exim4/
chmod 750 /var/spool/exim4/
mkdir /var/spool/exim4_outgoing/
chown Debian-exim:Debian-exim /var/spool/exim4_outgoing/
chmod 750 /var/spool/exim4_outgoing/

chown Debian-exim:Debian-exim /var/spool/exim4/db
chmod 750 /var/spool/exim4/db
chown Debian-exim:Debian-exim /var/spool/exim4/input
chmod 750 /var/spool/exim4/input
chown Debian-exim:Debian-exim /var/spool/exim4/msglog
chmod 750 /var/spool/exim4/msglog

mkdir /var/spool/exim4_outgoing/db
chown Debian-exim:Debian-exim /var/spool/exim4_outgoing/db
chmod 750 /var/spool/exim4_outgoing/db
mkdir /var/spool/exim4_outgoing/input
chown Debian-exim:Debian-exim /var/spool/exim4_outgoing/input
chmod 750 /var/spool/exim4_outgoing/input
mkdir /var/spool/exim4_outgoing/msglog
chown Debian-exim:Debian-exim /var/spool/exim4_outgoing/msglog
chmod 750 /var/spool/exim4_outgoing/msglog

# save currrent crontabs
crontab -l > /tmp/crontab.current
# add exim crons
cat >> /tmp/crontab.current << EOF
# For Exim4 ingoing
0 6 * * * /usr/sbin/exim_tidydb -t 1d /var/spool/exim4 callout > /dev/null 2>&1
# For Exim4 outgoing
0 6 * * * /usr/sbin/exim_tidydb -t 1d /var/spool/exim4_outgoing retry > /dev/null 2>&1
0 6 * * * /usr/sbin/exim_tidydb -t 1d /var/spool/exim4_outgoing wait-remote_smtp > /dev/null 2>&1
EOF
# import the update cron
crontab /tmp/crontab.current
rm /tmp/crontab.current

if [ -z $(grep -r "mailer-daemon: postmaster" /etc/aliases) ]; then
    echo "mail-daemon: postmaster" >> /etc/aliases
fi
if [ -z $(grep -r "postmaster: root" /etc/aliases) ]; then
    echo "postmaster: root" >> /etc/aliases
fi
if [ -z $(grep -r "hostmaster: root" /etc/aliases) ]; then
    echo "hostmaster: root" >> /etc/aliases
fi
if [ -z $(grep -r "webmaster: root" /etc/aliases) ]; then
    echo "webmaster: root" >> /etc/aliases
fi
if [ -z $(grep -r "www: root" /etc/aliases) ]; then
    echo "www: root" >> /etc/aliases
fi
if [ -z $(grep -r "clamav: root" /etc/aliases) ]; then
    echo "clamav: root" >> /etc/aliases
fi

/etc/init.d/exim4 stop
