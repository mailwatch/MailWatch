#Migration to UTF8 MySQL Database

When you created MailWatch database was your MySQL instance configured to use `utf8` charset and `utf8_unicode_ci` collation?
Then you can skip this whole readme and tool directory, this procedure is not for you.

To upgrade a not utf8 existing install to the new utf8 enabled MailWatch 1.2 you need to run an upgrade query on your database:

* Find a good downtime window, this process may take a while when run on big installations
* Create a backup of your MailWatch database, safety first!
* Check your MySQL version: if it is at least 5.5.3 you can have full support of utf8 charset. If you're not so lucky you should use `upgrade_mysql_db_to_utf8.sql` file instead 
* Edit first line of `upgrade_mysql_db_to_utf8mb4.sql` and set your database name (default is `mailscanner`)
* Execute `upgrade_mysql_db_to_utf8mb4.sql` on your MailWatch database (`mysql --user=mailwatch -p --database=mailscanner < upgrade_mysql_db_to_utf8mb4.sql`)


**Attention! Max username lenght is reduced to 250 chars when using utf8mb4 (MySQL version >= 5.5.3)!!!**