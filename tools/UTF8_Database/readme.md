#Migration to UTF8 MySQL Database

When you created MailWatch database was your MySQL instance configure to use `utf8` charset and `utf8_general_ci` collation?
Then you can skip this whole readme and tool directory, this procedure is not for you.

To upgrade a not utf8 existing install to the new utf8 enabled MailWatch 1.2 you need to run an upgrade query on your database:

* Create a backup of your MailWatch database, safety first!
* Edit first line of `upgrade_mysql_db_to_utf8.sql` and set your database name (default is `mailscanner`)
* Execute `upgrade_mysql_db_to_utf8.sql` on your MailWatch database (`mysql --user=mailwatch -p --database=mailscanner < upgrade_mysql_db_to_utf8.sql`)
