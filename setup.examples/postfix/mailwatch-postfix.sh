#!/bin/bash
# Configuration script for mailwatch with postfix
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

cp "$DIR/etc/MailScanner/conf.d/mailwatch.conf" /etc/MailScanner/conf.d/mailwatch.conf 
cp "$DIR/etc/postfix/header_checks" /etc/postfix/header_checks
echo "header_checks = regexp:/etc/postfix/header_checks" >> /etc/postfix/main.cf

# restart required to create hold folder (will create error message because of still missing permissions)
/etc/init.d/postfix restart

# "Setting file permissions for use of postfix"
mkdir -p /var/spool/MailScanner/spamassassin/
chown -R postfix:mtagroup /var/spool/MailScanner/spamassassin/
chown -R postfix:www-data /var/spool/postfix/incoming/
chown -R postfix:www-data /var/spool/postfix/hold
chmod -R g+r /var/spool/postfix/hold
chmod -R g+r /var/spool/postfix/incoming/
chown -R postfix.postfix /var/spool/MailScanner/incoming
chown -R postfix.postfix /var/spool/MailScanner/quarantine

# restart again to notice new permissions
/etc/init.d/postfix restart
