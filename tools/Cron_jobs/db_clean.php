#!/usr/bin/php -q
<?php
/*
 MailWatch for MailScanner
 Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

// Change the following to reflect the location of functions.php
require('/var/www/html/mailscanner/functions.php');

ini_set('error_log', 'syslog');
ini_set('html_errors', 'off');
ini_set('display_errors', 'on');
ini_set('implicit_flush', 'false');

if (!defined('RECORD_DAYS_TO_KEEP') || RECORD_DAYS_TO_KEEP < 1) {
    die("The variable RECORD_DAYS_TO_KEEP is empty, please set a value in conf.php.");
} elseif (!defined('AUDIT_DAYS_TO_KEEP') || AUDIT_DAYS_TO_KEEP < 1) {
    die("The variable AUDIT_DAYS_TO_KEEP is empty, please set a value in conf.php.");
} else {
    // Cleaning the maillog table
    dbquery("DELETE LOW_PRIORITY FROM maillog WHERE timestamp < (NOW() - INTERVAL " . RECORD_DAYS_TO_KEEP . " DAY)");

    // Cleaning the mta_log and optionally the mta_log_id table
    $sqlcheck = "SHOW TABLES LIKE 'mtalog_ids'";
    $tablecheck = dbquery($sqlcheck);
    $mta = get_conf_var('mta');
    $optimize_mtalog_id = '';
    if ($mta == 'postfix' && mysql_num_rows($tablecheck) > 0) {
        //version for postfix with mtalog_ids enabled
        dbquery(
            "DELETE i.*, m.* FROM mtalog AS m
             LEFT OUTER JOIN mtalog_ids AS i ON i.smtp_id = m.msg_id
             WHERE m.timestamp < (NOW() - INTERVAL " . RECORD_DAYS_TO_KEEP . " DAY)"
        );
        $optimize_mtalog_id = ', mtalog_ids';
    } else {
        dbquery("DELETE FROM mtalog WHERE timestamp < (NOW() - INTERVAL " . RECORD_DAYS_TO_KEEP . " DAY)");
    }

    // Clean the audit log
    dbquery("DELETE FROM audit_log WHERE timestamp < (NOW() - INTERVAL " . AUDIT_DAYS_TO_KEEP . " DAY)");

    // Optimize all of tables
    dbquery("OPTIMIZE TABLE maillog, mtalog, audit_log" . $optimize_mtalog_id);
}
