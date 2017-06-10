<?php

/*
 * MailWatch for MailScanner
 * Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 * Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 * Copyright (C) 2014-2017  MailWatch Team (https://github.com/mailwatch/1.2.0/graphs/contributors)
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

namespace MailWatch;

// Include of necessary functions
require_once __DIR__ . '/functions.php';

// Authentication checking
require __DIR__ . '/login.function.php';

if ($_SESSION['user_type'] !== 'A') {
    header('Location: index.php');
    audit_log(__('auditlog52', true));
} else {
    html_start(__('mwandmsversion52'), 0, false, false);
    $mailwatch_version = mailwatch_version();
    $mailscanner_version = get_conf_var('MailScannerVersionNumber');
    $php_version = PHP_VERSION;
    $mysql_version = database::mysqli_result(dbquery('SELECT VERSION()'), 0);
    $geoipv4_version = false;
    $geoipv6_version = false;
    if (file_exists('./temp/GeoIP.dat')) {
        $geoipv4_version = date('r', filemtime('./temp/GeoIP.dat')) . ' (' . __('downloaddate52') . ')';
    }
    if (file_exists('./temp/GeoIPv6.dat')) {
        $geoipv6_version = date('r', filemtime('./temp/GeoIPv6.dat')) . ' (' . __('downloaddate52') . ')';
    }

    echo '<table width="100%" class="boxtable">' . "\n";
    echo '<tr><th>' . __('softver11') . '</th></tr>' . "\n";
    echo '<tr>' . "\n";
    echo '<td>' . "\n";

    echo '<br>' . "\n";

    // Add test for OS
    if (0 === stripos(PHP_OS, 'linux')) {
        $vars = array();
        $files = glob('/etc/*-release');
        foreach ($files as $file) {
            $lines = array_filter(array_map(function ($line) {
                $parts = explode('=', $line);
                if (count($parts) !== 2) {
                    return false;
                }
                $parts[1] = str_replace(array('"', "'"), '', $parts[1]);
                $parts[1] = trim($parts[1]);
                return $parts;
            }, file($file)));
            foreach ($lines as $line) {
                $vars[$line[0]] = $line[1];
            }
        }
        if (isset($vars['ID']) && in_array(strtolower($vars['ID']), array('centos', 'debian'), true)) {
            echo __('systemos11') . ' ' . $vars['PRETTY_NAME'] . '<br>' . "\n";
            echo '<br>' . "\n";
        }
        if (isset($vars['ID']) && strtolower($vars['ID']) === 'ubuntu') {
            echo __('systemos11') . ' ' . $vars['NAME'] . ' ' . $vars['VERSION'] . '<br>' . "\n";
            echo '<br>' . "\n";
        }
    }
    if (strtolower(PHP_OS) === 'freebsd') {
        echo __('systemos11') . ' ' . php_uname('s') . ' ' . php_uname('r') . ' ' . php_uname('m') . '<br>' . "\n";
        echo '<br>' . "\n";
    }

    echo 'MailWatch ' . __('version11') . ' ' . $mailwatch_version . '<br>' . "\n";
    echo '<br>' . "\n";
    echo 'MailScanner ' . __('version11') . ' ' . $mailscanner_version . '<br>' . "\n";
    echo '<br>';
    $virusScanner = get_conf_var('VirusScanners');

    // Add test for others virus scanners.
    if (preg_match('/clam/i', $virusScanner)) {
        echo 'ClamAV ' . __('version11') . ' ';
        passthru(get_virus_conf('clamav') . " -V | cut -d/ -f1 | cut -d' ' -f2");
        echo '<br>' . "\n";
    }

    echo '<br>' . "\n";
    echo 'SpamAssassin ' . __('version11') . ' ';
    passthru(SA_DIR . "spamassassin -V | tr '\\\n' ' ' | cut -d' ' -f3");
    echo '<br>' . "\n";
    echo '<br>' . "\n";
    echo 'PHP ' . __('version11') . ' ' . $php_version . '<br>' . "\n";
    echo '<br>' . "\n";
    echo 'MySQL ' . __('version11') . ' ' . $mysql_version . '<br>' . "\n";
    echo '<br>' . "\n";
    echo 'GeoIP Database ' . __('version11') . ' ';
    if (false !== $geoipv4_version) {
        echo $geoipv4_version;
    } else {
        echo __('nodbdown11') . ' ';
    }
    echo "<br>\n<br>\n";
    echo 'GeoIPv6 Database ' . __('version11') . ' ';
    if (false !== $geoipv6_version) {
        echo $geoipv6_version;
    } else {
        echo __('nodbdown11');
    }
    echo "<br>\n<br>\n";
    echo '</td>' . "\n";
    echo '</tr>' . "\n";
    echo '</table>' . "\n";

    // Add footer
    html_end();
    // Close any open db connections
    dbclose();
}
