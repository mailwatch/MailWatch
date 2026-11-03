#!/usr/bin/php -q
<?php

/**
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

// Auto-detect functions.php relative to this script, with fallback to default path
$pathToFunctions = dirname(dirname(__DIR__)) . '/mailscanner/functions.php';
if (!@is_file($pathToFunctions)) {
    // Edit if you changed webapp directory from default
    $pathToFunctions = '/var/www/html/mailscanner/functions.php';
}
if (!@is_file($pathToFunctions)) {
    exit('Error: Cannot find functions.php file in "' . $pathToFunctions . '": edit ' . __FILE__ . ' and set the right path' . PHP_EOL);
}
require $pathToFunctions;

if (!defined('MAXMIND_LICENSE_KEY') || !validateInput(MAXMIND_LICENSE_KEY, 'maxmind')) {
    $error_message = __('geoipnokey15') . "\n\n";
    exit($error_message);
}

require_once MAILWATCH_HOME . '/geoip_update.inc.php';

$extract_dir = MAILWATCH_HOME . '/temp/';

ob_start();
$result = geoip_update_download($extract_dir, "\n");
if (true !== $result) {
    exit($result . "\n");
}

// Apply MailWatch rights on files from the last run
if (is_file('/etc/sudoers.d/mailwatch')) {
    $mwUID = exec(
        'cat /etc/sudoers.d/mailwatch | grep "User_Alias MAILSCANNER" | sed "s/.*= \(.*\).*/\1/"',
        $output_cat,
        $retval_cat
    );
    if ($retval_cat > 0) {
        exit(__('nofind52') . '.' . "\n");
    }

    $path = $extract_dir . 'GeoLite2-Country.mmdb';
    passthru("chown $mwUID.$mwUID $path", $retval_chown);
    if ($retval_chown > 0) {
        exit(__('nofindowner52') . ' ' . $extract_dir . "\n");
    }
} else {
    echo __('nosudoerfile52') . ' ' . $extract_dir . "\n";
}

echo __('processok52') . "\n\n";
ob_flush();
flush();
