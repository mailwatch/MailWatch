#!/bin/bash
# Script to apply adjustments for exim
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
Webuser="$1"
OS="$2"
if [ "$OS" == "Debian" ] || [ "$OS" == "Ubuntu" ]; then
    EximUser="Debian-exim"
    EximGroup="Debian-exim"
    Service="exim4"
else
    if [ "$OS" == "RedHat" ]; then
        sed -i -e "s/Debian-exim/exim/" "$DIR/etc/MailScanner/conf.d/mailwatch.conf"
        sed -i -e "s~/usr/sbin/exim4~/usr/sbin/exim~" "$DIR/etc/MailScanner/conf.d/mailwatch.conf"
    fi
    EximUser="exim"
    EximGroup="exim"
    Service="exim"
fi

service "$Service" stop

mkdir -p /etc/exim4/conf.d/main/
cp -f "$DIR/etc/default/exim4" /etc/default/exim4
cp  -f "$DIR"/etc/exim4/mailscanner_acldefs /etc/exim4/.
cp  -f "$DIR"/etc/exim4/hubbed_hosts /etc/exim4/.
cp  -f "$DIR"/etc/exim4/relay_domains /etc/exim4/.
cp  -f "$DIR"/etc/exim4/conf.d/main/00_mailscanner_listmacrosdefs /etc/exim4/conf.d/main/.
cp  -f "$DIR"/etc/exim4/conf.d/main/01_mailscanner_config /etc/exim4/conf.d/main/.
cp "$DIR/etc/MailScanner/conf.d/mailwatch.conf" /etc/MailScanner/conf.d/mailwatch.conf

usermod -a -G "$EximGroup" clamav
usermod -a -G mtagroup clamav
usermod -a -G mtagroup "$EximUser"
usermod -a -G mtagroup mail
usermod -a -G mtagroup "$Webuser"

chown -R root:root /etc/exim4
chmod -R 644 /etc/exim4
chmod 755 /etc/exim4/conf.d/
chown root:"$EximGroup" /etc/exim4/passwd.client
chmod 640 /etc/exim4/passwd.client

chown "$EximUser":adm /var/log/exim4/
chmod 750 /var/log/exim4/
chown "$EximUser":adm /var/log/exim4_outgoing/
chmod 750 /var/log/exim4_outgoing/

chown "$EximUser":"$EximGroup" /var/spool/exim4/
chmod 750 /var/spool/exim4/
mkdir /var/spool/exim4_outgoing/
chown "$EximUser":"$EximGroup" /var/spool/exim4_outgoing/
chmod 750 /var/spool/exim4_outgoing/

chown "$EximUser":"$EximGroup" /var/spool/exim4/db
chmod 750 /var/spool/exim4/db
chown "$EximUser":"$EximGroup" /var/spool/exim4/input
chmod 750 /var/spool/exim4/input
chown "$EximUser":"$EximGroup" /var/spool/exim4/msglog
chmod 750 /var/spool/exim4/msglog

mkdir /var/spool/exim4_outgoing/db
chown "$EximUser":"$EximGroup" /var/spool/exim4_outgoing/db
chmod 750 /var/spool/exim4_outgoing/db
mkdir /var/spool/exim4_outgoing/input
chown "$EximUser":"$EximGroup" /var/spool/exim4_outgoing/input
chmod 750 /var/spool/exim4_outgoing/input
mkdir /var/spool/exim4_outgoing/msglog
chown "$EximUser":"$EximGroup" /var/spool/exim4_outgoing/msglog
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

if [[ -z $(grep -r "mailer-daemon: postmaster" /etc/aliases) ]]; then
    echo "mail-daemon: postmaster" >> /etc/aliases
fi
if [[ -z $(grep -r "postmaster: root" /etc/aliases) ]]; then
    echo "postmaster: root" >> /etc/aliases
fi
if [[ -z $(grep -r "hostmaster: root" /etc/aliases) ]]; then
    echo "hostmaster: root" >> /etc/aliases
fi
if [[ -z $(grep -r "webmaster: root" /etc/aliases) ]]; then
    echo "webmaster: root" >> /etc/aliases
fi
if [[ -z $(grep -r "www: root" /etc/aliases) ]]; then
    echo "www: root" >> /etc/aliases
fi
if [[ -z $(grep -r "clamav: root" /etc/aliases) ]]; then
    echo "clamav: root" >> /etc/aliases
fi

service "$Service" start
