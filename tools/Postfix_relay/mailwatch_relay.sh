#!/bin/bash

/usr/bin/php -q /var/www/html/mailscanner/postfix_relay.php --refresh
/usr/bin/php -q /var/www/html/mailscanner/mailscanner_relay.php --refresh
