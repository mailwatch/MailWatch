#!/bin/bash
# Script to apply adjustments for exim
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

cp "$DIR/etc/default/exim4" /etc/default/exim4
cp -R "$DIR"/etc/exim/* /etc/exim4/
cp "$DIR/etc/MailScanner/conf.d/mailwatch.conf" /etc/MailScanner/conf.d/mailwatch.conf

usermod -a -G Debian-exim clamav
usermod -a -G mtagroup clamav
usermod -a -G mtagroup Debian-exim
usermod -a -G mtagroup mail 
usermod -a -G mtagroup www-data

chown -R root:root /etc/exim4
chmod -R 644 /etc/exim4
chmod 755 /etc/exim4/conf.d/
chmod 755 /etc/exim4/eximconfig/
chown root:Debian-exim /etc/exim4/exim.key
chmod 640 /etc/exim4/exim.key
chown root:Debian-exim /etc/exim4/passwd.client
chmod 640 /etc/exim4/passwd.client

mkdir -p /var/log/exim4/
mkdir -p /var/log/exim4_outgoing/
chown Debian-exim:adm /var/log/exim4/
chmod 750 /var/log/exim4/
chown Debian-exim:adm /var/log/exim4_outgoing/
chmod 750 /var/log/exim4_outgoing/

chown Debian-exim:Debian-exim /var/spool/exim4/
chmod 750 /var/spool/exim4/
chown Debian-exim:Debian-exim /var/spool/exim4_outgoing/
chmod 750 /var/spool/exim4_outgoing/

chown Debian-exim:Debian-exim /var/spool/exim4/db
chmod 750 /var/spool/exim4/db
chown Debian-exim:Debian-exim /var/spool/exim4/input
chmod 750 /var/spool/exim4/input
chown Debian-exim:Debian-exim /var/spool/exim4/msglog
chmod 750 /var/spool/exim4/msglog
chown Debian-exim:Debian-exim /var/spool/exim4/exim-process.info
chmod 640 /var/spool/exim4/exim-process.info

chown Debian-exim:Debian-exim /var/spool/exim4_outgoing/db
chmod 750 /var/spool/exim4_outgoing/db
chown Debian-exim:Debian-exim /var/spool/exim4_outgoing/input
chmod 750 /var/spool/exim4_outgoing/input
chown Debian-exim:Debian-exim /var/spool/exim4_outgoing/msglog
chmod 750 /var/spool/exim4_outgoing/msglog
chown Debian-exim:Debian-exim /var/spool/exim4_outgoing/exim-process.info
chmod 640 /var/spool/exim4_outgoing/exim-process.info

chown Debian-exim:Debian-exim /var/log/exim4/db/callout
chmod 640 /var/spool/exim4/db/callout
chown Debian-exim:Debian-exim /var/log/exim4/db/callout.lockfile
chmod 640 /var/spool/exim4/db/callout.lockfile

chown root:root /var/spool/exim4_outgoing/db/retry
chmod 640 /var/spool/exim4_outgoing/db/retry
chown Debian-exim:Debian-exim /var/spool/exim4_outgoing/db/retry.lockfile
chmod 640 /var/spool/exim4_outgoing/db/retry.lockfile
chown Debian-exim:Debian-exim /var/spool/exim4_outgoing/db/wait-remote_smtp
chmod 640 /var/spool/exim4_outgoing/db/wait-remote_smtp
chown Debian-exim:Debian-exim /var/spool/exim4_outgoing/db/wait-remote_smtp.lockfile
chmod 640 /var/spool/exim4_outgoing/db/wait-remote_smtp.lockfile

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
