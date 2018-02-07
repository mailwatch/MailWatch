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

$required_constant = array('TIME_ZONE', 'QUARANTINE_DAYS_TO_KEEP');
$required_constant_missing_count = 0;
foreach ($required_constant as $constant) {
    if (!defined($constant)) {
        echo sprintf(__('message62'), $constant) . "\n";
        $required_constant_missing_count++;
    }
}
if ($required_constant_missing_count === 0) {
    date_default_timezone_set(TIME_ZONE);

    ini_set('error_log', 'syslog');
    ini_set('html_errors', 'off');
    ini_set('display_errors', 'on');
    ini_set('implicit_flush', 'false');

    function quarantine_reconcile()
    {
        $quarantine = get_conf_var('QuarantineDir');
        $d = dir($quarantine) or die($php_errormsg);
        while (false !== ($f = $d->read())) {
            if (preg_match('/^\d{8}$/', $f) && is_array($array = quarantine_list_dir($f))) {
                foreach ($array as $id) {
                    dbg("Updating: $id");
                    $sql = "UPDATE maillog SET quarantined=1 WHERE id='$id'";
                    dbquery($sql);
                }
            }
        }
    }

    function quarantine_clean()
    {
        $oldest = date('U', strtotime('-' . QUARANTINE_DAYS_TO_KEEP . ' days'));
        $quarantine = get_conf_var('QuarantineDir');

        $d = dir($quarantine) or die($php_errormsg);
        while (false !== ($f = $d->read())) {
            // Only interested in quarantine directories (yyyymmdd)
            if (preg_match('/^\d{8}$/', $f)) {
                $unixtime = quarantine_date_to_unixtime($f);
                if ($unixtime < $oldest) {
                    // Needs to be deleted
                    $array = quarantine_list_dir($f);
                    dbg("Processing directory $f: found " . count($array) . ' records to delete');
                    foreach ($array as $id) {
                        // Update the quarantine flag
                        $sql = "UPDATE maillog SET quarantined = NULL WHERE id='$id'";
                        dbquery($sql);
                        //If auto quarantine release is enabled, remove from autorelease table when quarantined email expires
                        if (defined('AUTO_RELEASE') && AUTO_RELEASE === true) {
                            $sql = "DELETE FROM autorelease WHERE msg_id = '$id'";
                            dbquery($sql);
                        }
                    }
                    dbg('Deleting: ' . escapeshellarg($quarantine . '/' . $f));
                    exec('rm -rf ' . escapeshellarg($quarantine . '/' . $f), $output, $return);
                    if ($return > 0) {
                        echo __('error62') . " $output\n";
                    }
                }
            }
        }
        $d->close();
    }

    function quarantine_date_to_unixtime($dirname)
    {
        $y = substr($dirname, 0, 4);
        $m = substr($dirname, 4, 2);
        $d = substr($dirname, 6, 2);

        return mktime(0, 0, 0, $m, $d, $y);
    }

    function dbg($text)
    {
        if (DEBUG) {
            echo $text . "\n";
        }
    }

    function quarantine_list_dir($dir)
    {
        $dir = get_conf_var('QuarantineDir') . "/$dir";
        $spam = "$dir/spam";
        $nonspam = "$dir/nonspam";
        $mcp = "$dir/mcp";
        $array = array();

        if (is_dir($dir)) {
            // Main quarantine
            $d = dir($dir) or die($php_errormsg);
            while (false !== ($f = $d->read())) {
                if ($f !== '.' && $f !== '..' && $f !== 'spam' && $f !== 'nonspam' && $f !== 'mcp') {
                    //dbg("Found $dir/$f");
                    $array[] = $f;
                }
            }
            $d->close();
        }

        if (is_dir($spam)) {
            // Spam folder
            $d = dir($spam) or die($php_errormsg);
            while (false !== ($f = $d->read())) {
                if ($f !== '.' && $f !== '..' && $f !== 'spam' && $f !== 'nonspam' && $f !== 'mcp') {
                    //dbg("Found $spam/$f");
                    $array[] = $f;
                }
            }
            $d->close();
        }

        if (is_dir($nonspam)) {
            $d = dir($nonspam) or die($php_errormsg);
            while (false !== ($f = $d->read())) {
                if ($f !== '.' && $f !== '..' && $f !== 'spam' && $f !== 'nonspam' && $f !== 'mcp') {
                    //dbg("Found $nonspam/$f");
                    $array[] = $f;
                }
            }
            $d->close();
        }

        if (is_dir($mcp)) {
            $d = dir($mcp) or die($php_errormsg);
            while (false !== ($f = $d->read())) {
                if ($f !== '.' && $f !== '..' && $f !== 'spam' && $f !== 'nonspam' && $f !== 'mcp') {
                    //dbg("Found $mcp/$f");
                    $array[] = $f;
                }
            }
            $d->close();
        }

        return $array;
    }

    if ($_SERVER['argc'] !== 1 && $_SERVER['argc'] <= 2) {
        switch ($_SERVER['argv'][1]) {
            case '--clean':
                quarantine_clean();
                break;
            case '--reconsile': //deprecated option
            case '--reconcile':
                quarantine_reconcile();
                break;
            default:
                die('Usage: ' . $_SERVER['argv'][0] . ' [--clean] [--reconcile]' . "\n");
        }
    } else {
        die('Usage: ' . $_SERVER['argv'][0] . ' [--clean] [--reconcile]' . "\n");
    }
}
