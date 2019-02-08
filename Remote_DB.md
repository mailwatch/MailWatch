# How to configure MailScanner to log to a remote MySQL database

This document presumes that you will have a server acting as a database with PHP and MySQL installed on it, and multiple MailScanner gateways logging to the database server.

1)  Follow the steps from https://docs.mailwatch.org/install/installing.html to create the database on the database server.

2)  Create a mailscanner user and password on the database server:  
    % mysql mailscanner  
    mysql> GRANT ALL ON mailscanner.* TO mailwatch IDENTIFIED BY 'password';  
    mysql> GRANT FILE ON *.* TO mailwatch IDENTIFIED BY '<password>';
    mysql> flush privileges;

3)  On each MailScanner gateway, you'll need to make sure that the mysql client, perl, perl DBI and perl DBD-Mysql (4.032 or higher) are installed: see https://docs.mailwatch.org/install/getting-started.html

4)  From one of the MailScanner gateway, verify you can connect to the db:  
    % mysql mailscanner -u mailwatch -h <db_hostname> -p  
    Enter password: *******  
    If you get a mysql> prompt, you can connect correctly (enter \q to quit).

5)  On each MailScanner gateway continue following the install instructions at https://docs.mailwatch.org/install/installing.html#create-a-mysql-user-and-password--set-up-mailscanner-for-sql-logging

7)  On each MailWatch system set RPC_ALLOWED_CLIENTS in conf.php to a list of IP addresses of each MailWatch system.

8) (Optional) If you want a combined display of the number of mails in the mail queues also set RPC_REMOTE_SERVER in conf.php
