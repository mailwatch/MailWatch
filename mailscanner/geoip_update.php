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
                       ' . \MailWatch\Translation::__('message115') . ' <a href="http://dev.maxmind.com/geoip/legacy/geolite/" target="_maxmind">MaxMind</a> ' . \MailWatch\Translation::__('message215') . '<br><br>
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

    $files_base_url = 'http://geolite.maxmind.com';
    $files['ipv4']['description'] = \MailWatch\Translation::__('geoipv415');
    $files['ipv4']['path'] = '/download/geoip/database/GeoLiteCountry/GeoIP.dat.gz';
    $files['ipv4']['destination'] = __DIR__ . '/temp/GeoIP.dat.gz';
    $files['ipv6']['description'] =  \MailWatch\Translation::__('geoipv615');
    $files['ipv6']['path'] = '/download/geoip/database/GeoIPv6.dat.gz';
    $files['ipv6']['destination'] = __DIR__ . '/temp/GeoIPv6.dat.gz';

    $extract_dir = __DIR__ . '/temp/';

    // Clean-up from last run
    foreach ($files as $file) {
        if (file_exists($file['destination'])) {
            unlink($file['destination']);
        }
    }
    ob_flush();
    flush();

    if (!file_exists($files['ipv4']['destination']) && !file_exists($files['ipv6']['destination'])) {
        if (is_writable($extract_dir) && is_readable($extract_dir)) {
            if (function_exists('fsockopen') || extension_loaded('curl')) {
                $requestSession = new Requests_Session($files_base_url . '/');
                $requestSession->useragent = 'MailWatch/' . str_replace(
                    [' - ', ' '],
                    ['-', '-'],
                        mailwatch_version()
                );

                if (USE_PROXY === true) {
                    $requestSession->options['proxy']['type'] = 'HTTP';
                    if (PROXY_USER !== '') {
                        $requestSession->options['proxy']['authentication'] = [
                            PROXY_SERVER . ':' . PROXY_PORT,
                            PROXY_USER,
                            PROXY_PASS
                        ];
                    } else {
                        $requestSession->options['proxy']['authentication'] = [
                            PROXY_SERVER . ':' . PROXY_PORT
                        ];
                    }
                }

                foreach ($files as $file) {
                    try {
                        $requestSession->filename = $file['destination'];
                        $result = $requestSession->get($file['path']);
                        if ($result->success === true) {
                            echo $file['description'] . ' ' . \MailWatch\Translation::__('downok15') . '<br>' . "\n";
                        }
                    } catch (Requests_Exception $e) {
                        echo \MailWatch\Translation::__('downbad15') . ' ' . $file['description'] . \MailWatch\Translation::__('colon99') . ' ' . $e->getMessage() . "<br>\n";
                    }

                    ob_flush();
                    flush();
                }

                echo \MailWatch\Translation::__('downokunpack15') . '<br>' . "\n";
                ob_flush();
                flush();
            } elseif (!in_array('exec', array_map('trim', explode(',', ini_get('disable_functions'))), true)) {
                // wget
                $proxyString = '';
                if (USE_PROXY) {
                    if (PROXY_USER !== '') {
                        $proxyString = '-e use_proxy=on -e http_proxy=' . PROXY_SERVER . ':' . PROXY_PORT . ' --proxy-user=' . PROXY_USER . ' --proxy-password=' . PROXY_PASS;
                    } else {
                        $proxyString = '-e use_proxy=on -e http_proxy=' . PROXY_SERVER . ':' . PROXY_PORT;
                    }
                }

                foreach ($files as $file) {
                    exec(
                        'wget ' . $proxyString . ' -N ' . $files_base_url . $file['path'] . ' -O ' . $file['destination'],
                        $output_wget,
                        $retval_wget
                    );
                    if ($retval_wget > 0) {
                        echo \MailWatch\Translation::__('downbad15') . ' ' . $file['description'] . "<br>\n";
                    } else {
                        echo $file['description'] . ' ' . \MailWatch\Translation::__('downok15') . '<br>' . "\n";
                    }
                }
            } else {
                $error_message = \MailWatch\Translation::__('message315') . "<br>\n";
                $error_message .= \MailWatch\Translation::__('message415');
                die($error_message);
            }
            // Extract files
            echo "<br>\n";
            if (function_exists('gzopen')) {
                foreach ($files as $file) {
                    $zp_gz = gzopen($file['destination'], 'r');
                    $targetFile = fopen(str_replace('.gz', '', $file['destination']), 'wb');
                    while ($string = gzread($zp_gz, 4096)) {
                        fwrite($targetFile, $string, strlen($string));
                    }
                    gzclose($zp_gz);
                    fclose($targetFile);
                    echo $file['description'] . ' ' . \MailWatch\Translation::__('unpackok15') . '<br>' . "\n";
                    unlink($file['destination']);
                    ob_flush();
                    flush();
                }
            } elseif (!in_array('exec', array_map('trim', explode(',', ini_get('disable_functions'))), true)) {
                foreach ($files as $file) {
                    exec('gunzip -f ' . $file['destination'], $output_gunzip, $retval_gunzip);
                    if ($retval_gunzip > 0) {
                        die(\MailWatch\Translation::__('extractnotok15') . $file['description'] . "<br>\n");
                    }

                    echo $file['description'] . ' ' . \MailWatch\Translation::__('extractok15') . '<br>' . "\n";
                }
            } else {
                // Unable to extract the file correctly
                $error_message = \MailWatch\Translation::__('message515') . "<br>\n";
                $error_message .= \MailWatch\Translation::__('message615');
                die($error_message);
            }

            echo \MailWatch\Translation::__('processok15') . "\n";
            ob_flush();
            flush();
            \MailWatch\Security::audit_log(\MailWatch\Translation::__('auditlog15', true));
        } else {
            // Unable to read or write to the directory
            die(\MailWatch\Translation::__('norread15') . ' ' . $extract_dir . ' ' . \MailWatch\Translation::__('directory15') . ".\n");
        }
    } else {
        $error_message = \MailWatch\Translation::__('message715') . "<br>\n";
        $error_message .= \MailWatch\Translation::__('message815') . " $extract_dir" . '.';
        die($error_message);
    }
}

// Add the footer
\MailWatch\Html::end();
// Close the connection to the Database
\MailWatch\Db::close();
