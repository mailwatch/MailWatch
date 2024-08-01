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

// Require files
require_once __DIR__ . '/functions.php';

// Authentication verification
require __DIR__ . '/login.function.php';

html_start(__('geoipupdate15'), 0, false, false);

if (!defined('MAXMIND_LICENSE_KEY') || !defined('MAXMIND_ACCOUNT_ID') || !validateInput(MAXMIND_LICENSE_KEY, 'maxmind')) {
    $error_message = __('geoipnokey15') . '<br>' . "\n";
    exit($error_message);
} elseif (!isset($_POST['run'])) {
    echo '<form method="POST" action="geoip_update.php">
            <input type="hidden" name="run" value="true">
            <table class="boxtable" width="100%">
            <tr><th>';
    echo __('updategeoip15');
    echo '</th></tr>
               <tr>
                   <td>
                    <br>
                       ' . __('message115') . ' <a href="https://dev.maxmind.com/geoip/geoip2/geolite2/" target="_maxmind">MaxMind</a> ' . __('message215') . '<br><br>
                   </td>
               </tr>
               <tr>
                   <td align="center"><br><input type="SUBMIT" value="' . __('input15') . '"><br><br></td>
               </tr>
            </table>
            </form>' . "\n";
} else {
    ob_start();
    echo __('downfile15') . '<br>' . "\n";

    $urlSchema = 'https://';
    $downloadServer = 'download.maxmind.com';
    $file['description'] = __('geoip15');
    $file['path'] = '/geoip/databases/GeoLite2-Country/download?suffix=tar.gz';
    $file['destination'] = __DIR__ . '/temp/GeoLite2-Country.tar.gz';
    $file['destinationFileName'] = 'GeoLite2-Country.mmdb';

    $extract_dir = __DIR__ . '/temp/';

    // Clean-up from last run
    if (file_exists($file['destination'])) {
        unlink($file['destination']);
        @unlink(substr($file['destination'], 0, -3));
    }
    ob_flush();
    flush();

    if (!file_exists($file['destination'])) {
        if (is_writable($extract_dir) && is_readable($extract_dir)) {
            if (function_exists('fsockopen') || extension_loaded('curl')) {
                $ch = curl_init($urlSchema . $downloadServer . $file['path']);
                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_USERNAME, MAXMIND_ACCOUNT_ID);
                curl_setopt($ch, CURLOPT_PASSWORD, MAXMIND_LICENSE_KEY);
                curl_setopt($ch, CURLOPT_USERAGENT, 'MailWatch/' . mailwatch_version());
                if (defined('USE_PROXY') && USE_PROXY === true) {
                    curl_setopt($ch, CURLOPT_PROXY, PROXY_SERVER);
                    curl_setopt($ch, CURLOPT_PROXYPORT, PROXY_PORT);
                    if (PROXY_USER !== '') {
                        curl_setopt($ch, CURLOPT_PROXYUSERPWD, PROXY_USER . ':' . PROXY_PASS);
                    }

                    switch (PROXY_TYPE) {
                        case 'HTTP':
                        case 'CURLPROXY_HTTP': // BC for old constant name
                            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
                            break;
                        case 'SOCKS5':
                        case 'CURLPROXY_SOCKS5': // BC for old constant name
                            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
                            break;
                        default:
                            exit(__('dieproxy15'));
                    }
                }

                try {
                    $fpDestinationFile = fopen($file['destination'], 'w');
                    curl_setopt($ch, CURLOPT_FILE, $fpDestinationFile);
                    curl_exec($ch);
                    if (empty(curl_error($ch))) {
                        echo $file['description'] . ' ' . __('downok15') . '<br>' . "\n";
                    } else {
                        echo __('downbad15') . ' ' . $file['description'] . __('colon99') . ' ' . curl_error($ch) . "<br>\n";
                    }
                } catch (Exception $e) {
                    echo __('downbad15') . ' ' . $file['description'] . __('colon99') . ' ' . curl_error($ch) . "<br>\n";
                } finally {
                    fclose($fpDestinationFile);
                }

                ob_flush();
                flush();

                echo __('downokunpack15') . '<br>' . "\n";
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

                $command = escapeshellcmd('wget ' . $proxyString . ' -N ' . $urlSchema . MAXMIND_ACCOUNT_ID . ':' . MAXMIND_LICENSE_KEY . '@' . $downloadServer . $file['path'] . ' -O ' . $file['destination']);
                $result = exec(
                    $command,
                    $output_wget,
                    $retval_wget
                );

                if ($retval_wget > 0) {
                    echo __('downbad15') . ' ' . $file['description'] . "<br>\n";
                } else {
                    echo $file['description'] . ' ' . __('downok15') . '<br>' . "\n";
                }
            } else {
                $error_message = __('message315') . '<br>' . "\n" . __('message415');
                exit($error_message);
            }

            // Extract files
            echo '<br>' . "\n";
            if (class_exists('PharData')) {
                $p = new PharData($file['destination']);
                $p->decompress();
                $phar = new PharData(substr($file['destination'], 0, -3));
                $phar->extractTo($extract_dir, null, true);
                echo $file['description'] . ' ' . __('unpackok15') . '<br>' . "\n";
                unlink($file['destination']);
                unlink(substr($file['destination'], 0, -3));

                foreach (new DirectoryIterator($extract_dir) as $item) {
                    if ($item->isDot()) {
                        continue;
                    }

                    if ($item->isDir()) {
                        $extractedFolder = $item->getFilename();
                        if (rename($extract_dir . $extractedFolder . '/' . $file['destinationFileName'], $extract_dir . $file['destinationFileName'])) {
                            array_map('unlink', glob($extract_dir . $extractedFolder . '/*'));
                            rmdir($extract_dir . $extractedFolder);
                        }
                    }
                }
            } else {
                // Unable to extract the file correctly
                $error_message = __('message515') . "<br>\n" . __('message615');
                exit($error_message);
            }

            echo __('processok15') . "\n";
            ob_flush();
            flush();
            audit_log(__('auditlog15', true));
        } else {
            // Unable to read or write to the directory
            exit(__('norread15') . ' ' . $extract_dir . ' ' . __('directory15') . ".\n");
        }
    } else {
        $error_message = __('message715') . "<br>\n";
        $error_message .= __('message815') . " $extract_dir" . '.';
        exit($error_message);
    }
}

// Add the footer
html_end();
// Close the connection to the Database
dbclose();
