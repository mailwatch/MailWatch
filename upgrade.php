#!/usr/bin/php -q
<?php

/*
 * MailWatch for MailScanner
 * Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 * Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 * Copyright (C) 2014-2015  MailWatch Team (https://github.com/orgs/mailwatch/teams/team-stable)
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.
 *
 * In addition, as a special exception, the copyright holder gives permission to link the code of this program with
 * those files in the PEAR library that are licensed under the PHP License (or with modified versions of those files
 * that use the same license as those files), and distribute linked combinations including the two.
 * You must obey the GNU General Public License in all respects for all of the code used other than those files in the
 * PEAR library that are licensed under the PHP License. If you modify this program, you may extend this exception to
 * your version of the program, but you are not obligated to do so.
 * If you do not wish to do so, delete this exception statement from your version.
 *
 * As a special exception, you have permission to link this program with the JpGraph library and distribute executables,
 * as long as you follow the requirements of the GNU GPL in regard to all of the software in the executable aside from
 * JpGraph.
 *
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free
 * Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

header("Content-type: text/plain\n\n");
require("/var/www/html/mailscanner/functions.php");

function pad($input)
{
    $input = str_pad($input, 70, ".", STR_PAD_RIGHT);

    return $input;
}

function executeQuery($sql)
{
    if (@mysql_query($sql)) {
        echo " OK\n";
    } else {
        echo " ERROR\n";
        die("MySQL error: " . @mysql_error());
    }
}

function check_utf8_table($db, $table)
{
    $sql = 'SELECT c.character_set_name
            FROM information_schema.tables AS t, information_schema.collation_character_set_applicability AS c
            WHERE c.collation_name = t.table_collation
            AND t.table_schema = "' . mysql_real_escape_string($db) . '"
            AND t.table_name = "' . mysql_real_escape_string($table) . '"';
    $result = @mysql_query($sql);

    if (strtolower(mysql_result($result, 0)) === 'utf8') {
        mysql_free_result($result);

        return true;
    }

    return false;
}

function getTableIndexes($table)
{
    $sql = 'SHOW INDEX FROM `' . $table . '`';
    $result = @mysql_query($sql);

    $indexes = array();
    if (!$result || mysql_num_rows($result) === 0) {
        return $indexes;
    }

    while ($row = mysql_fetch_assoc($result)) {
        $indexes[] = $row['Key_name'];
    }

    return $indexes;
}

$errors = false;

// Test connectivity to the database
echo pad("Testing connectivity to the database ");
if (($link = @mysql_connect(DB_HOST, DB_USER, DB_PASS)) && @mysql_select_db(DB_NAME)) {
    echo " OK\n";
    // Update schema at this point
    echo "Updating database schema: \n";

    /*
    ** Updates to the schema for 1.2.0
    */

    echo pad(" - Convert database to UTF-8");
    $sql = "ALTER DATABASE `" . DB_NAME . "` CHARACTER SET = utf8 COLLATE = utf8_unicode_ci";
    executeQuery($sql);

    $utf8_tables = array(
        "audit_log",
        "blacklist",
        "inq",
        "maillog",
        "mcp_rules",
        "mtalog",
        "outq",
        "sa_rules",
        "saved_filters",
        "spamscores",
        "user_filters",
        "users",
        "whitelist",
    );

    foreach ($utf8_tables as $table) {
        echo pad(" - Convert table `" . $table . "` to UTF-8");
        if (check_utf8_table(DB_NAME, $table) === false) {
            $sql = "ALTER TABLE `" . $table . "` CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci";
            executeQuery($sql);
        } else {
            echo " SKIPPING\n";
        }
    }

    echo pad(" - Enlarge username field in `audit_log` table");
    $sql = "ALTER TABLE `audit_log` CHANGE `user` `user` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT ''";
    executeQuery($sql);

    echo pad(" - Enlarge password field in `users` table");
    $sql = "ALTER TABLE `users` CHANGE `password` `password` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT ''";
    executeQuery($sql);

    echo pad(" - Enlarge username field in `users` table");
    $sql = "ALTER TABLE `users` CHANGE `username` `username` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT ''";
    executeQuery($sql);

    echo pad(" - Enlarge fullname field in `users` table");
    $sql = "ALTER TABLE `users` CHANGE `fullname` `fullname` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT ''";
    executeQuery($sql);

    echo pad(" - Enlarge username field in `user_filters` table");
    $sql = "ALTER TABLE `user_filters` CHANGE `username` `username` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT ''";
    executeQuery($sql);

    echo pad(" - Enlarge user field in `spamscores` table");
    $sql = "ALTER TABLE `spamscores` CHANGE `user` `user` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT ''";
    executeQuery($sql);

    echo pad(" - Drop `geoip_country` table");

    $sql = "DROP TABLE IF EXISTS `geoip_country`";
    executeQuery($sql);

    // check for missing indexes
    $indexes = array(
        'maillog' => array(
            'maillog_datetime_idx' => '(`date`,`time`)',
            'maillog_id_idx' => '(`id`(20))',
            'maillog_clientip_idx' => '(`clientip`(20))',
            'maillog_from_idx' => '(`from_address`(200))',
            'maillog_to_idx' => '(`to_address`(200))',
            'maillog_host' => '(`hostname`(30))',
            'from_domain_idx' => '(`from_domain`(50))',
            'to_domain_idx' => '(`to_domain`(50))',
            'maillog_quarantined' => '(`quarantined`)',
            'timestamp_idx' => '(`timestamp`)'
        )
    );

    foreach ($indexes as $table => $indexlist) {
        $existingIndexes = getTableIndexes($table);
        foreach ($indexlist as $indexname => $value) {
            if (!in_array($indexname, $existingIndexes)) {
                $sql = 'ALTER TABLE `' . $table . '` ADD KEY `' . $indexname . '` ' . $value . ';';
                executeQuery($sql);
            }
        }
    }

    /*
    ** Updates to the schema for 1.0.3
    *

    echo pad(" - Adding hostname column to inq table ");
    $sql = "ALTER TABLE inq ADD COLUMN (hostname TEXT)";
    executeQuery($sql);

    echo pad(" - Adding hostname column to outq table ");
    $sql = "ALTER TABLE outq ADD COLUMN (hostname TEXT)";
    executeQuery($sql);

    echo pad(" - Adding index to inq table ");
    $sql = "ALTER TABLE inq ADD INDEX inq_hostname (hostname(50))";
    executeQuery($sql);

    echo pad(" - Adding index to outq table ");
    $sql = "ALTER TABLE outq ADD INDEX outq_hostname (hostname(50))";
    executeQuery($sql);

    echo pad(" - Creating table spamscores ");
    $sql = "CREATE TABLE spamscores (
  user VARCHAR(40) NOT NULL DEFAULT '',
  lowspamscore DECIMAL(10,0) NOT NULL DEFAULT '0',
  highspamscore DECIMAL(10,0) NOT NULL DEFAULT '0',
  PRIMARY KEY  (user)
) ENGINE=MyISAM";
    executeQuery($sql);

    echo pad(" - Adding columns to the users table ");
    $sql = "ALTER TABLE users ADD COLUMN (
  spamscore TINYINT(4) DEFAULT '0',
  highspamscore TINYINT(4) DEFAULT '0',
  noscan TINYINT(1) DEFAULT '0',
  quarantine_rcpt VARCHAR(60) DEFAULT NULL
)";
    executeQuery($sql);

    /*
    **  Updates to the schema for 1.0
    **

    // Create backup of maillog
    echo pad(" - Creating backup of maillog table to maillog_pre");
    $sql = "DROP TABLE maillog_pre";
    @mysql_query($sql); // Don't care if this succeeds or not
    $sql = "CREATE TABLE maillog_pre AS SELECT * FROM maillog";
    executeQuery($sql);

    // Drop the indexes
    echo pad(" - Dropping indexes on maillog table");
    $sql = "SHOW INDEX FROM maillog";
    $result = dbquery($sql);
    while ($row = mysql_fetch_object($result)) {
        // Use key name as array key to remove dups...
        $indexes[$row->Key_name] = true;
    }
    $drop_index = join(', DROP INDEX ', array_keys($indexes));
    $sql = "ALTER TABLE maillog DROP INDEX $drop_index";
    executeQuery($sql);

    // Alter the database structure

    echo pad(" - Adding new columns to maillog table ");
    $sql = "ALTER TABLE maillog ADD COLUMN (
    quarantined TINYINT(1) DEFAULT '0',
    mcpsascore DECIMAL(7,2) DEFAULT '0.00',
    isfp TINYINT(1) DEFAULT '0',
    ismcp TINYINT(1) DEFAULT '0',
    mcpwhitelisted TINYINT(1) DEFAULT '0',
    ishighmcp TINYINT(1) DEFAULT '0',
    mcpblacklisted TINYINT(1) DEFAULT '0',
    from_domain TEXT,
    mcpreport TEXT,
    to_domain TEXT,
    isfn TINYINT(1) DEFAULT '0',
    issamcp TINYINT(1) DEFAULT '0')";
    executeQuery($sql);

    echo pad(" - Populating from_domain and to_domain column ");
    $sql = "UPDATE maillog SET timestamp=timestamp, from_domain=SUBSTRING_INDEX(from_address,'@',-1), to_domain=SUBSTRING_INDEX(to_address,'@',-1)";
    executeQuery($sql);

    echo pad(" - Creating indexes ");
    $sql = "
   ALTER TABLE maillog
    ADD INDEX maillog_datetime_idx (date,time),
    ADD INDEX maillog_id_idx (id(20)),
    ADD INDEX maillog_clientip_idx (clientip(20)),
    ADD INDEX maillog_from_idx (from_address(200)),
    ADD INDEX maillog_to_idx (to_address(200)),
    ADD INDEX maillog_host_idx (hostname(30)),
    ADD INDEX maillog_from_domain_idx (from_domain(50)),
    ADD INDEX maillog_to_domain_idx (to_domain(50)),
    ADD INDEX maillog_quarantined_idx (quarantined)
   ";
    executeQuery($sql);

    echo pad(" - Dropping 'relay' table ");
    $sql = "DROP TABLE relay";
    executeQuery($sql);

    echo pad(" - Altering 'user_filters' table ");
    $sql = "ALTER TABLE user_filters CHANGE COLUMN username username VARCHAR(60) NOT NULL DEFAULT ''";
    executeQuery($sql);

    echo pad(" - Altering 'users' table (1) ");
    $sql = "ALTER TABLE users CHANGE COLUMN username username VARCHAR(60) NOT NULL DEFAULT ''";
    executeQuery($sql);

    echo pad(" - Altering 'users' table (2) ");
    $sql = "ALTER TABLE users CHANGE COLUMN type type ENUM('A','D','U','R','H') DEFAULT 'U'";
    executeQuery($sql);

    echo pad(" - Altering 'users' table (3) ");
    $sql = "ALTER TABLE users ADD COLUMN quarantine_report TINYINT(1) DEFAULT '0'";
    executeQuery($sql);

    echo pad(" - Creating table 'audit_log' ");
    $sql = "
   CREATE TABLE audit_log (
     timestamp TIMESTAMP NOT NULL,
     user VARCHAR(20) NOT NULL DEFAULT '',
     ip_address VARCHAR(15) NOT NULL DEFAULT '',
     action TEXT NOT NULL
   ) ENGINE=MyISAM
   ";
    executeQuery($sql);

    echo pad(" - Creating table 'blacklist' ");
    $sql = "
   CREATE TABLE blacklist (
     id INT(11) NOT NULL AUTO_INCREMENT,
     to_address TEXT,
     to_domain TEXT,
     from_address TEXT,
     PRIMARY KEY  (id),
     UNIQUE KEY blacklist_uniq (to_address(100),from_address(100))
   ) ENGINE=MyISAM
   ";
    executeQuery($sql);

    echo pad(" - Creating table 'geoip_country' ");
    $sql = "
   CREATE TABLE geoip_country (
     begin_ip VARCHAR(15) DEFAULT NULL,
     end_ip VARCHAR(15) DEFAULT NULL,
     begin_num BIGINT(20) DEFAULT NULL,
     end_num BIGINT(20) DEFAULT NULL,
     iso_country_code CHAR(2) DEFAULT NULL,
     country TEXT,
     KEY geoip_country_begin (begin_num),
     KEY geoip_country_end (end_num)
   ) ENGINE=MyISAM
   ";
    executeQuery($sql);

    echo pad(" - Creating table 'mcp_rules' ");
    $sql = "
   CREATE TABLE mcp_rules (
     rule CHAR(100) NOT NULL DEFAULT '',
     rule_desc CHAR(200) NOT NULL DEFAULT '',
     PRIMARY KEY  (rule)
   ) ENGINE=MyISAM
   ";
    executeQuery($sql);

    echo pad(" - Creating table 'mtalog' ");
    $sql = "
   CREATE TABLE mtalog (
     timestamp DATETIME DEFAULT NULL,
     host TEXT,
     type TEXT,
     msg_id VARCHAR(20) DEFAULT NULL,
     relay TEXT,
     dsn TEXT,
     status TEXT,
     delay TIME DEFAULT NULL,
     UNIQUE KEY mtalog_uniq (timestamp,host(10),type(10),msg_id,relay(20)),
     KEY mtalog_timestamp (timestamp),
     KEY mtalog_type (type(10))
   ) ENGINE=MyISAM
   ";
    executeQuery($sql);

    echo pad(" - Creating table 'saved_filters' ");
    $sql = "
   CREATE TABLE saved_filters (
     name TEXT NOT NULL,
     col TEXT NOT NULL,
     operator TEXT NOT NULL,
     value TEXT NOT NULL,
     username TEXT NOT NULL,
     UNIQUE KEY unique_filters (name(20),col(20),operator(20),value(20),username(20))
   ) ENGINE=MyISAM
   ";
    executeQuery($sql);

    echo pad(" - Creating table 'whitelist' ");
    $sql = "
   CREATE TABLE whitelist (
     id INT(11) NOT NULL AUTO_INCREMENT,
     to_address TEXT,
     to_domain TEXT,
     from_address TEXT,
     PRIMARY KEY  (id),
     UNIQUE KEY whitelist_uniq (to_address(100),from_address(100))
   ) ENGINE=MyISAM
   ";
    executeQuery($sql);

    *
    ** Finished
    */

    // Phew! - finished
    mysql_close($link);
} else {
    echo " FAILED\n";
    $errors[] = "Database connection failed: " . @mysql_error();
}

echo "\n";

// Check MailScanner settings
echo "Checking MailScanner.conf settings: \n";
$check_settings = array(
    'QuarantineWholeMessage' => 'yes',
    'QuarantineWholeMessagesAsQueueFiles' => 'no',
    'DetailedSpamReport' => 'yes',
    'IncludeScoresInSpamAssassinReport' => 'yes',
    'SpamActions' => 'store',
    'HighScoringSpamActions' => 'store',
    'AlwaysLookedUpLast' => '&MailWatchLogging'
);
foreach ($check_settings as $setting => $value) {
    echo pad(" - $setting ");
    if (preg_match('/' . $value . '/', get_conf_var($setting))) {
        echo " OK\n";
    } else {
        echo " WARNING\n";
        $errors[] = "MailScanner.conf: $setting != $value (=" . get_conf_var($setting) . ")";
    }
}
echo "\n";

if (is_array($errors)) {
    echo "*** ERROR/WARNING SUMMARY ***\n";
    foreach ($errors as $error) {
        echo $error . "\n";
    }
}
