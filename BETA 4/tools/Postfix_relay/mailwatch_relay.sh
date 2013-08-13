#!/bin/bash

/usr/bin/php -qc/etc/php.local /home/www/sites/mailscanner/postfix_relay.php --refresh
/usr/bin/php -qc/etc/php.local /home/www/sites/mailscanner/mailscanner_relay.php --refresh
