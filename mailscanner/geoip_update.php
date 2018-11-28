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

// Require files
use GuzzleHttp\Exception\RequestException;
use MailWatch\GeoIp;

//require_once __DIR__ . '/functions.php';

// Authentication verification
//require __DIR__ . '/login.function.php';

\MailWatch\Html::start(\MailWatch\Translation::__('geoipupdate15'), 0, false, false);

if (!isset($_POST['run'])) {
    echo '<form method="POST" action="geoip_update.php">
            <input type="hidden" name="run" value="true">
            <table class="boxtable" width="100%">
            <tr><th>';
    echo \MailWatch\Translation::__('updategeoip15');
    echo '</th></tr>
               <tr>
                   <td>
                    <br>
                       ' . \MailWatch\Translation::__('message115') . ' <a href="https://dev.maxmind.com/geoip/geoip2/geolite2/" target="_maxmind">MaxMind</a> ' . \MailWatch\Translation::__('message215') . '<br><br>
                   </td>
               </tr>
               <tr>
                   <td align="center"><br><input type="SUBMIT" value="' . \MailWatch\Translation::__('input15') . '"><br><br></td>
               </tr>
            </table>
            </form>' . "\n";
} else {
    ob_start();
    echo \MailWatch\Translation::__('downfile15') . '<br>' . "\n";

    $geoIp = new GeoIp();
    // Clean-up from last run
    $geoIp->cleanupFiles();

    if (!file_exists($geoIp::$savePath['database'])) {
        $httpClient = $geoIp->getDownloadClient(USE_PROXY, PROXY_SERVER . ':' . PROXY_PORT, PROXY_USER, PROXY_PASS);

        try {
            $geoIp->downloadFiles($httpClient);
            echo $geoIp->download['database'] . ' ' . \MailWatch\Translation::__('downok15') . '<br>' . "\n";
            $geoIp->verifySignature($geoIp::$savePath['database'], $geoIp::$savePath['md5']);

            $geoIp->decompressArchive();

            // save file to correct location adn delete other extracted files
            $extractedFolder = $geoIp->moveDatabaseFile();

            $geoIp->cleanupFiles($extractedFolder);

            echo \MailWatch\Translation::__('processok15') . "\n";

            ob_flush();
            flush();
            \MailWatch\Security::audit_log(\MailWatch\Translation::__('auditlog15', true));
        } catch (RequestException $e) {
            echo \MailWatch\Translation::__('downbad15') . ' ' . $e->getRequest()->getUri() . \MailWatch\Translation::__('colon99') . ' ';
            //echo Psr7\str($e->getRequest());
            if ($e->hasResponse()) {
                echo $e->getResponse()->getStatusCode() . ' ' . $e->getResponse()->getReasonPhrase();
            }
            echo "<br>\n";
        } catch (\BadMethodCallException $e) {
            echo $e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage() . "<br>\n";
        } catch (\InvalidArgumentException $e) {
            echo $e->getMessage() . "<br>\n";
        }
    } else {
        $error_message = \MailWatch\Translation::__('message715') . "<br>\n" . \MailWatch\Translation::__('message815') . ' ' . $geoIp::$savePath['database'] . '.';
        die($error_message);
    }

    ob_flush();
    flush();
}

// Add the footer
\MailWatch\Html::end();
// Close the connection to the Database
\MailWatch\Db::close();
