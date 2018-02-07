#!/usr/bin/php -q
<?php

/*
 * MailWatch for MailScanner
 * Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 * Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 * Copyright (C) 2014-2018  MailWatch Team (https://github.com/mailwatch/1.2.0/graphs/contributors)
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
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free
 * Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

// Edit if you changed webapp directory from default
$pathToFunctions = '/var/www/html/mailscanner/functions.php';
if (!@is_file($pathToFunctions)) {
    die('Error: Cannot find functions.php file in "' . $pathToFunctions . '": edit ' . __FILE__ . ' and set the right path on line ' . (__LINE__ - 3) . PHP_EOL);
}
require $pathToFunctions;

ini_set('error_log', 'syslog');
ini_set('html_errors', 'off');
ini_set('display_errors', 'on');
ini_set('implicit_flush', 'false');

if (!defined('RECORD_DAYS_TO_KEEP') || RECORD_DAYS_TO_KEEP < 1) {
    die('The variable RECORD_DAYS_TO_KEEP is empty, please set a value in conf.php.');
}

if (!defined('AUDIT_DAYS_TO_KEEP') || AUDIT_DAYS_TO_KEEP < 1) {
    die('The variable AUDIT_DAYS_TO_KEEP is empty, please set a value in conf.php.');
}

// Cleaning the maillog table
dbquery('DELETE LOW_PRIORITY FROM maillog WHERE timestamp < (NOW() - INTERVAL ' . RECORD_DAYS_TO_KEEP . ' DAY)');

// Cleaning the mta_log and optionally the mta_log_id table
$sqlcheck = "SHOW TABLES LIKE 'mtalog_ids'";
$tablecheck = dbquery($sqlcheck);
$mta = get_conf_var('mta');
$optimize_mtalog_id = '';
if ($mta === 'postfix' && $tablecheck->num_rows > 0) {
    //version for postfix with mtalog_ids enabled
    dbquery(
        'DELETE i.*, m.* FROM mtalog AS m
         LEFT OUTER JOIN mtalog_ids AS i ON i.smtp_id = m.msg_id
         WHERE m.timestamp < (NOW() - INTERVAL ' . RECORD_DAYS_TO_KEEP . ' DAY)'
    );
    $optimize_mtalog_id = ', mtalog_ids';
} else {
    dbquery('DELETE FROM mtalog WHERE timestamp < (NOW() - INTERVAL ' . RECORD_DAYS_TO_KEEP . ' DAY)');
}

// Clean the audit log
dbquery('DELETE FROM audit_log WHERE timestamp < (NOW() - INTERVAL ' . AUDIT_DAYS_TO_KEEP . ' DAY)');

// Optimize all of tables
dbquery('OPTIMIZE TABLE maillog, mtalog, audit_log' . $optimize_mtalog_id);
