![](/mailscanner/images/mailwatch-logo.png?raw=true)

# MailWatch for MailScanner

MailWatch for MailScanner is a web-based front-end to MailScanner written in PHP, MySQL, Chart.js and and others usefull libraries 

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

It comes with a CustomConfig modules for MailScanner which causes MailScanner to log all message data (excluding body text email) to a MySQL database which is then queried by MailWatch for reporting and statistics.

## Features

* Displays the inbound/outbound mail queue size (currently for Exim, Postfix or Sendmail), Load Average and Today's Totals for Messages, Spam, Viruses and Blocked Content on each page header.
* Colour-coded display of recently processed mail.
* Drill-down onto each message to see detailed information.
* Quarantine management allows you to release, delete or run SpamAssasin `sa-learn` across any quarantined messages.
* Reports with customisable filters and graphs by Chart.js.
* Tools to view Virus Scanner status, MySQL database status and to view the MailScanner configuration files.
* Utilities for Postfix and Sendmail to monitor and display the mail queue sizes and to record and display message relay information.
* Multiple user levels: user, domain and admin that limit the data and features available to each.
* XML-RPC support that allows multiple MailScanner/MailWatch installations to act as one.
* Works with MySQL 5.5+ / MariaDB, PHP 5.4 to PHP 7, and have been tested on most popular Linux distributions (Debian/Ubuntu, CentOS and RedHat).


## Developed with the help of

* eFa - email Filter appliance
* HtmlPurifier
* IPSet
* ircmaxell/password_compat
* Chart.js
* MaxMind GeoIP
* Pear Mail
* Pear Pager
* PHP-XMLRPC
* Requests for PHP
* znk3r/hash_equals
