#!/usr/bin/env php
<?php

/*
 * MailWatch for MailScanner
 * Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 * Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 * Copyright (C) 2014-2021  MailWatch Team (https://github.com/mailwatch/1.2.0/graphs/contributors)
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
$pathToFunctions = __DIR__ . '/../../mailscanner/functions.php';
if (!@is_file($pathToFunctions)) {
    exit('Error: Cannot find functions.php file in "' . $pathToFunctions . '": edit ' . __FILE__ . ' and set the right path on line ' . (__LINE__ - 3) . PHP_EOL);
}
require $pathToFunctions;

ini_set('error_log', 'syslog');
ini_set('html_errors', 'off');
ini_set('display_errors', 'on');
ini_set('implicit_flush', 'false');

if (!defined('RECORD_DAYS_TO_KEEP') || RECORD_DAYS_TO_KEEP < 1) {
    exit('The variable RECORD_DAYS_TO_KEEP is empty, please set a value in conf.php.');
}

if (!defined('AUDIT_DAYS_TO_KEEP') || AUDIT_DAYS_TO_KEEP < 1) {
    exit('The variable AUDIT_DAYS_TO_KEEP is empty, please set a value in conf.php.');
}

// Batch size for DELETE operations (reduces table locking on large databases)
$batchSize = defined('DB_CLEAN_BATCH_SIZE') ? (int)DB_CLEAN_BATCH_SIZE : 10000;

// Whether to run OPTIMIZE TABLE after cleanup
$runOptimize = !defined('DB_CLEAN_OPTIMIZE') || DB_CLEAN_OPTIMIZE === true;

/**
 * Delete rows in batches to avoid long table locks
 *
 * @param string $table       Table name
 * @param string $whereClause WHERE clause (without WHERE keyword)
 * @param int    $batchSize   Number of rows to delete per batch (0 = no batching)
 *
 * @return int Total number of deleted rows
 */
function deleteInBatches(string $table, string $whereClause, int $batchSize): int
{
    $totalDeleted = 0;

    if ($batchSize <= 0) {
        // No batching - delete all in one query
        $result = dbquery("DELETE LOW_PRIORITY FROM {$table} WHERE {$whereClause}");

        return $result->affected_rows ?? 0;
    }

    // Delete in batches
    do {
        $result = dbquery("DELETE LOW_PRIORITY FROM {$table} WHERE {$whereClause} LIMIT {$batchSize}");
        $deleted = $result->affected_rows ?? 0;
        $totalDeleted += $deleted;
    } while ($deleted > 0);

    return $totalDeleted;
}

// Cleaning the maillog table
deleteInBatches('maillog', 'timestamp < (NOW() - INTERVAL ' . RECORD_DAYS_TO_KEEP . ' DAY)', $batchSize);

// Cleaning the mta_log and optionally the mta_log_id table
$sqlcheck = "SHOW TABLES LIKE 'mtalog_ids'";
$tablecheck = dbquery($sqlcheck);
$mta = get_conf_var('mta');
$optimize_mtalog_id = '';
if (('postfix' === $mta || 'msmail' === $mta) && $tablecheck->num_rows > 0) {
    // version for postfix with mtalog_ids enabled
    // Delete mtalog_ids entries that reference old mtalog records, then delete from mtalog
    if ($batchSize <= 0) {
        // No batching - delete all in one query using subquery
        dbquery(
            'DELETE FROM mtalog_ids WHERE smtp_id IN (
                SELECT msg_id FROM mtalog WHERE timestamp < (NOW() - INTERVAL ' . RECORD_DAYS_TO_KEEP . ' DAY)
            )'
        );
        dbquery('DELETE LOW_PRIORITY FROM mtalog WHERE timestamp < (NOW() - INTERVAL ' . RECORD_DAYS_TO_KEEP . ' DAY)');
    } else {
        // With batching: first collect msg_ids to delete in batches, then delete from mtalog_ids
        do {
            // Get a batch of msg_ids to delete
            $result = dbquery(
                'SELECT msg_id FROM mtalog WHERE timestamp < (NOW() - INTERVAL ' . RECORD_DAYS_TO_KEEP . ' DAY) LIMIT ' . $batchSize
            );

            if (0 === $result->num_rows) {
                break;
            }

            $msgIds = [];
            while ($row = $result->fetch_assoc()) {
                if (null !== $row['msg_id']) {
                    $msgIds[] = "'" . addslashes($row['msg_id']) . "'";
                }
            }

            if (!empty($msgIds)) {
                $msgIdList = implode(',', $msgIds);
                // Delete from mtalog_ids first
                dbquery('DELETE FROM mtalog_ids WHERE smtp_id IN (' . $msgIdList . ')');
                // Then delete from mtalog
                dbquery('DELETE LOW_PRIORITY FROM mtalog WHERE msg_id IN (' . $msgIdList . ')');
            }
        } while ($result->num_rows > 0);
    }
    $optimize_mtalog_id = ', mtalog_ids';
} else {
    deleteInBatches('mtalog', 'timestamp < (NOW() - INTERVAL ' . RECORD_DAYS_TO_KEEP . ' DAY)', $batchSize);
}

// Clean the audit log
deleteInBatches('audit_log', 'timestamp < (NOW() - INTERVAL ' . AUDIT_DAYS_TO_KEEP . ' DAY)', $batchSize);

// Optimize tables (optional - can be slow on large InnoDB tables)
if ($runOptimize) {
    dbquery('OPTIMIZE TABLE maillog, mtalog, audit_log' . $optimize_mtalog_id);
}
