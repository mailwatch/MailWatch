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
use GuzzleHttp\Exception\RequestException;
use MailWatch\GeoIp;

$pathToFunctions = __DIR__ . '/../../mailscanner/functions.php';
if (!@is_file($pathToFunctions)) {
    die('Error: Cannot find functions.php file in "' . $pathToFunctions . '": edit ' . __FILE__ . ' and set the right path on line ' . (__LINE__ - 3) . PHP_EOL);
}
require $pathToFunctions;

echo \MailWatch\Translation::__('downfile15') . "\n";

$geoIp = new GeoIp();
// Clean-up from last run
$geoIp->cleanupFiles();

if (!file_exists($geoIp::$savePath['database'])) {
    if (is_writable($geoIp::$savePath['extractTo']) && is_readable($geoIp::$savePath['extractTo'])) {
        $httpClient = $geoIp->getDownloadClient(USE_PROXY, PROXY_SERVER . ':' . PROXY_PORT, PROXY_USER, PROXY_PASS);
        try {
            $geoIp->downloadFiles($httpClient);
            echo $geoIp->download['database'] . ' ' . \MailWatch\Translation::__('downok15') . "\n";
            $geoIp->verifySignature($geoIp::$savePath['database'], $geoIp::$savePath['md5']);

            $geoIp->decompressArchive();

            // save file to correct location adn delete other extracted files
            $extractedFolder = $geoIp->moveDatabaseFile();

            $geoIp->cleanupFiles($extractedFolder);

            // Apply MailWatch rights on files from the last run
            $mwUID = exec('cat /etc/sudoers.d/mailwatch | grep "User_Alias MAILSCANNER" | sed "s/.*= \(.*\).*/\1/"', $output_cat, $retval_cat);
            if ($retval_cat > 0) {
                die(\MailWatch\Translation::__('nofind52') . '.' . "\n");
            }

            $path = $geoIp::$savePath['mmdbFile'];
            passthru("chown $mwUID.$mwUID $path", $retval_chown);
            if ($retval_chown > 0) {
                die(\MailWatch\Translation::__('nofindowner52') . ' ' . $geoIp::$savePath['extractTo'] . '.' . "\n");
            }

            echo \MailWatch\Translation::__('processok52') . "\n\n";

            \MailWatch\Security::audit_log(\MailWatch\Translation::__('auditlog52', true));
        } catch (RequestException $e) {
            echo \MailWatch\Translation::__('downbad52') . ' ' . $e->getRequest()->getUri() . \MailWatch\Translation::__('colon99') . ' ';
            //echo Psr7\str($e->getRequest());
            if ($e->hasResponse()) {
                echo $e->getResponse()->getStatusCode() . ' ' . $e->getResponse()->getReasonPhrase();
            }
            echo "<br>\n";
        } catch (\BadMethodCallException $e) {
            echo $e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage() . "\n";
        } catch (\InvalidArgumentException $e) {
            echo $e->getMessage() . "\n";
        }
    } else {
        // Unable to read or write to the directory
        die(\MailWatch\Translation::__('norread52') . ' ' . $geoIp::$savePath['extractTo'] . ' ' . \MailWatch\Translation::__('directory52') . ".\n");
    }
} else {
    $error_message = \MailWatch\Translation::__('message752') . "\n";
    $error_message .= \MailWatch\Translation::__('message852') . ' ' . $geoIp::$savePath['extractTo'] . '.';
    die($error_message);
}
