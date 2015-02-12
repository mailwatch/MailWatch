#!/usr/bin/php -q
<?php

/*
 MailWatch for MailScanner
 Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 Copyright (C) 2014-2015  MailWatch Team (https://github.com/orgs/mailwatch/teams/team-stable)

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 In addition, as a special exception, the copyright holder gives permission to link the code of this program
 with those files in the PEAR library that are licensed under the PHP License (or with modified versions of those
 files that use the same license as those files), and distribute linked combinations including the two.
 You must obey the GNU General Public License in all respects for all of the code used other than those files in the
 PEAR library that are licensed under the PHP License. If you modify this program, you may extend this exception to
 your version of the program, but you are not obligated to do so.
 If you do not wish to do so, delete this exception statement from your version.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

header("Content-type: text/plain\n\n");
require("/var/www/html/mailscanner/functions.php");

function pad($input)
{
    $input = str_pad($input, 70, ".", STR_PAD_RIGHT);
    return $input;
}

// Test connectivity to the database
echo pad("Testing connectivity to the database ");
if (($link = @mysql_connect(DB_HOST, DB_USER, DB_PASS)) && @mysql_select_db(DB_NAME)) {
    echo " OK\n";
    // Update schema at this point
    echo "Updating database schema: \n";

    /*
    ** Updates to the schema for 1.0.3
    */

    echo pad(" - Adding hostname column to inq table ");
    $sql = "ALTER TABLE inq ADD COLUMN (hostname TEXT)";
    if (@mysql_query($sql)) {
        echo " OK\n";
    } else {
        echo " ERROR\n";
        die("MySQL error: " . @mysql_error());
    }

    echo pad(" - Adding hostname column to outq table ");
    $sql = "ALTER TABLE outq ADD COLUMN (hostname TEXT)";
    if (@mysql_query($sql)) {
        echo " OK\n";
    } else {
        echo " ERROR\n";
        die("MySQL error: " . @mysql_error());
    }

    echo pad(" - Adding index to inq table ");
    $sql = "ALTER TABLE inq ADD INDEX inq_hostname (hostname(50))";
    if (@mysql_query($sql)) {
        echo " OK\n";
    } else {
        echo " ERROR\n";
        die("MySQL error: " . @mysql_error());
    }

    echo pad(" - Adding index to outq table ");
    $sql = "ALTER TABLE outq ADD INDEX outq_hostname (hostname(50))";
    if (@mysql_query($sql)) {
        echo " OK\n";
    } else {
        echo " ERROR\n";
        die("MySQL error: " . @mysql_error());
    }

    echo pad(" - Creating table spamscores ");
    $sql = "CREATE TABLE spamscores (
  user varchar(40) NOT NULL default '',
  lowspamscore decimal(10,0) NOT NULL default '0',
  highspamscore decimal(10,0) NOT NULL default '0',
  PRIMARY KEY  (user)
) ENGINE=MyISAM";
    if (@mysql_query($sql)) {
        echo " OK\n";
    } else {
        echo " ERROR\n";
        die("MySQL error: " . @mysql_error());
    }

    echo pad(" - Adding columns to the users table ");
    $sql = "ALTER TABLE users ADD COLUMN (
  spamscore tinyint(4) default '0',
  highspamscore tinyint(4) default '0',
  noscan tinyint(1) default '0',
  quarantine_rcpt varchar(60) default NULL
)";
    if (@mysql_query($sql)) {
        echo " OK\n";
    } else {
        echo " ERROR\n";
        die("MySQL error: " . @mysql_error());
    }

    /*
    **  Updates to the schema for 1.0
    **

    // Create backup of maillog
    echo pad(" - Creating backup of maillog table to maillog_pre");
    $sql = "DROP TABLE maillog_pre";
    @mysql_query($sql); // Don't care if this succeeds or not
    $sql = "CREATE TABLE maillog_pre AS SELECT * FROM maillog";
    if(@mysql_query($sql)) {
     echo " OK\n";
    } else {
     echo " ERROR\n";
     die("MySQL error: ".@mysql_error());
    }

    // Drop the indexes
    echo pad(" - Dropping indexes on maillog table");
    $sql = "SHOW INDEX FROM maillog";
    $result = dbquery($sql);
    while($row=mysql_fetch_object($result)) {
     // Use key name as array key to remove dups...
     $indexes[$row->Key_name] = true;
    }
    $drop_index = join(', DROP INDEX ', array_keys($indexes));
    $sql = "ALTER TABLE maillog DROP INDEX $drop_index";
    if(@mysql_query($sql)) {
     echo " OK\n";
    } else {
     echo " ERROR\n";
     die("MySQL error: ".@mysql_error());
    }

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
    if(@mysql_query($sql)) {
     echo " OK\n";
    } else {
     echo " ERROR\n";
     die("MySQL error: ".@mysql_error());
    }

    echo pad(" - Populating from_domain and to_domain column ");
    $sql = "UPDATE maillog SET timestamp=timestamp, from_domain=SUBSTRING_INDEX(from_address,'@',-1), to_domain=SUBSTRING_INDEX(to_address,'@',-1)";
    if(@mysql_query($sql)) {
     echo " OK\n";
    } else {
     echo " ERROR\n";
     die("MySQL error: ".@mysql_error());
    }

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
    if(@mysql_query($sql)) {
     echo " OK\n";
    } else {
     echo " ERROR\n";
     die("MySQL error: ".@mysql_error());
    }

    echo pad(" - Dropping 'relay' table ");
    $sql = "DROP TABLE relay";
    if(@mysql_query($sql)) {
     echo " OK\n";
    } else {
     echo " ERROR\n";
     die("MySQL error: ".@mysql_error());
    }

    echo pad(" - Altering 'user_filters' table ");
    $sql = "ALTER TABLE user_filters CHANGE COLUMN username username varchar(60) NOT NULL default ''";
    if(@mysql_query($sql)) {
     echo " OK\n";
    } else {
     echo " ERROR\n";
     die("MySQL error: ".@mysql_error());
    }

    echo pad(" - Altering 'users' table (1) ");
    $sql = "ALTER TABLE users CHANGE COLUMN username username varchar(60) NOT NULL default ''";
    if(@mysql_query($sql)) {
     echo " OK\n";
    } else {
     echo " ERROR\n";
     die("MySQL error: ".@mysql_error());
    }

    echo pad(" - Altering 'users' table (2) ");
    $sql = "ALTER TABLE users CHANGE COLUMN type type enum('A','D','U','R','H') default 'U'";
    if(@mysql_query($sql)) {
     echo " OK\n";
    } else {
     echo " ERROR\n";
     die("MySQL error: ".@mysql_error());
    }

    echo pad(" - Altering 'users' table (3) ");
    $sql = "ALTER TABLE users ADD COLUMN quarantine_report TINYINT(1) DEFAULT '0'";
    if(@mysql_query($sql)) {
     echo " OK\n";
    } else {
     echo " ERROR\n";
     die("MySQL error: ".@mysql_error());
    }

    echo pad(" - Creating table 'audit_log' ");
    $sql = "
   CREATE TABLE audit_log (
     timestamp timestamp NOT NULL,
     user varchar(20) NOT NULL default '',
     ip_address varchar(15) NOT NULL default '',
     action text NOT NULL
   ) ENGINE=MyISAM
   ";
    if(@mysql_query($sql)) {
     echo " OK\n";
    } else {
     echo " ERROR\n";
     die("MySQL error: ".@mysql_error());
    }

    echo pad(" - Creating table 'blacklist' ");
    $sql = "
   CREATE TABLE blacklist (
     id int(11) NOT NULL auto_increment,
     to_address text,
     to_domain text,
     from_address text,
     PRIMARY KEY  (id),
     UNIQUE KEY blacklist_uniq (to_address(100),from_address(100))
   ) ENGINE=MyISAM
   ";
    if(@mysql_query($sql)) {
     echo " OK\n";
    } else {
     echo " ERROR\n";
     die("MySQL error: ".@mysql_error());
    }

    echo pad(" - Creating table 'geoip_country' ");
    $sql = "
   CREATE TABLE geoip_country (
     begin_ip varchar(15) default NULL,
     end_ip varchar(15) default NULL,
     begin_num bigint(20) default NULL,
     end_num bigint(20) default NULL,
     iso_country_code char(2) default NULL,
     country text,
     KEY geoip_country_begin (begin_num),
     KEY geoip_country_end (end_num)
   ) ENGINE=MyISAM
   ";
    if(@mysql_query($sql)) {
     echo " OK\n";
    } else {
     echo " ERROR\n";
     die("MySQL error: ".@mysql_error());
    }

    echo pad(" - Creating table 'mcp_rules' ");
    $sql = "
   CREATE TABLE mcp_rules (
     rule char(100) NOT NULL default '',
     rule_desc char(200) NOT NULL default '',
     PRIMARY KEY  (rule)
   ) ENGINE=MyISAM
   ";
    if(@mysql_query($sql)) {
     echo " OK\n";
    } else {
     echo " ERROR\n";
     die("MySQL error: ".@mysql_error());
    }

    echo pad(" - Creating table 'mtalog' ");
    $sql = "
   CREATE TABLE mtalog (
     timestamp datetime default NULL,
     host text,
     type text,
     msg_id varchar(20) default NULL,
     relay text,
     dsn text,
     status text,
     delay time default NULL,
     UNIQUE KEY mtalog_uniq (timestamp,host(10),type(10),msg_id,relay(20)),
     KEY mtalog_timestamp (timestamp),
     KEY mtalog_type (type(10))
   ) ENGINE=MyISAM
   ";
    if(@mysql_query($sql)) {
     echo " OK\n";
    } else {
     echo " ERROR\n";
     die("MySQL error: ".@mysql_error());
    }

    echo pad(" - Creating table 'saved_filters' ");
    $sql = "
   CREATE TABLE saved_filters (
     name text NOT NULL,
     col text NOT NULL,
     operator text NOT NULL,
     value text NOT NULL,
     username text NOT NULL,
     UNIQUE KEY unique_filters (name(20),col(20),operator(20),value(20),username(20))
   ) ENGINE=MyISAM
   ";
    if(@mysql_query($sql)) {
     echo " OK\n";
    } else {
     echo " ERROR\n";
     die("MySQL error: ".@mysql_error());
    }

    echo pad(" - Creating table 'whitelist' ");
    $sql = "
   CREATE TABLE whitelist (
     id int(11) NOT NULL auto_increment,
     to_address text,
     to_domain text,
     from_address text,
     PRIMARY KEY  (id),
     UNIQUE KEY whitelist_uniq (to_address(100),from_address(100))
   ) ENGINE=MyISAM
   ";
    if(@mysql_query($sql)) {
     echo " OK\n";
    } else {
     echo " ERROR\n";
     die("MySQL error: ".@mysql_error());
    }

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
