<img src="https://raw.github.com/mailwatch/1.2.0/master/mailscanner/images/mailwatch-logo.png" style="width:306px; height:79px; margin:auto;">

Mailwatch for MailScanner
=========

MailWatch for MailScanner is a web-based front-end to MailScanner written in PHP, MySQL and JpGraph and is available for free under the terms of the GNU Public License.

It comes with a CustomConfig module for MailScanner which causes MailScanner to log all message data (excluding body text) to a MySQL database which is then queried by MailWatch for reporting and statistics.

Features
=======

* Displays the inbound/outbound mail queue size (currently for Sendmail/Postfix/Exim users only), Load Average and Today's Totals for Messages, Spam, Viruses and Blocked Content on each page header.
* Colour-coded display of recently processed mail.
* Drill-down onto each message to see detailed information.
* Quarantine management allows you to release, delete or run `sa-learn` across any quarantined messages.
* Reports with customisable filters and graphs by JpGraph
* Tools to view Virus Scanner status, MySQL database status and to view the MailScanner configuration files.
* Utilities for Postfix and Sendmail to monitor and display the mail queue sizes and to record and display message relay information.
* Multiple user levels: user, domain and admin that limit the data and features available to each.
* XML-RPC support that allows multiple MailScanner/MailWatch installations to act as one.
