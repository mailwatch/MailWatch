#!/usr/bin/php -q
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

// Edit if you changed webapp directory from default
$pathToFunctions = '/var/www/html/mailscanner/functions.php';
if (!@is_file($pathToFunctions)) {
    die('Error: Cannot find functions.php file in "' . $pathToFunctions . '": edit ' . __FILE__ . ' and set the right path on line ' . (__LINE__ - 3) . PHP_EOL);
}
require $pathToFunctions;

require_once MAILWATCH_HOME . '/lib/request/Requests.php';
Requests::register_autoloader();

ob_start();
echo 'Downloading file, please wait...' . "\n";

$files_base_url = 'http://geolite.maxmind.com';
$files['ipv4']['description'] = __('geoipv452');
$files['ipv4']['path'] = '/download/geoip/database/GeoLiteCountry/GeoIP.dat.gz';
$files['ipv4']['destination'] = MAILWATCH_HOME . '/temp/GeoIP.dat.gz';
$files['ipv6']['description'] = __('geoipv652');
$files['ipv6']['path'] = '/download/geoip/database/GeoIPv6.dat.gz';
$files['ipv6']['destination'] = MAILWATCH_HOME . '/temp/GeoIPv6.dat.gz';

$extract_dir = MAILWATCH_HOME . '/temp/';

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
                if (PROXY_USER != '') {
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

                switch (PROXY_TYPE) {
                    case 'HTTP':
                    case 'CURLPROXY_HTTP': // BC for old constant name
                        // $requestProxy = new Requests_Proxy_HTTP($requestProxyParams);
                        $requestSession->options['proxy']['type'] = 'HTTP';
                        break;
                    case 'SOCKS5':
                    case 'CURLPROXY_SOCKS5': //BC for old constant name
                        $requestSession->options['proxy']['type'] = 'SOCKS5';
                        break;
                    default:
                        die(__('dieproxy52'));
                }
            }

            foreach ($files as $file) {
                try {
                    $requestSession->filename = $file['destination'];
                    $result = $requestSession->get($file['path']);
                    if ($result->success === true) {
                        echo $file['description'] . ' ' . __('downok52') . "\n";
                    }
                } catch (Requests_Exception $e) {
                    echo __('downbad52') . ' ' . $file['description'] . __('colon99') . ' ' . $e->getMessage() . "\n";
                }

                ob_flush();
                flush();
            }

            echo __('downokunpack52') . "\n";
            ob_flush();
            flush();
        } elseif (!in_array('exec', array_map('trim', explode(',', ini_get('disable_functions'))))) {
            // wget
            $proxyString = '';
            if (USE_PROXY) {
                if (PROXY_USER != '') {
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
                    echo __('downbad52') . ' ' . $file['description'] . "\n";
                } else {
                    echo $file['description'] . ' successfully downloaded' . "\n";
                }
            }
        } else {
            $error_message = __('message352') . "\n";
            $error_message .= __('message452');
            die($error_message);
        }
        // Extract files
        echo "\n";
        if (function_exists('gzopen')) {
            foreach ($files as $file) {
                $zp_gz = gzopen($file['destination'], 'r');
                $targetFile = fopen(str_replace('.gz', '', $file['destination']), 'wb');
                while ($string = gzread($zp_gz, 4096)) {
                    fwrite($targetFile, $string, strlen($string));
                }
                gzclose($zp_gz);
                fclose($targetFile);
                echo $file['description'] . ' ' . __('unpackok52') . "\n";
                unlink($file['destination']);
                ob_flush();
                flush();
            }
        } elseif (!in_array('exec', array_map('trim', explode(',', ini_get('disable_functions'))))) {
            foreach ($files as $file) {
                exec('gunzip -f ' . $file['destination'], $output_gunzip, $retval_gunzip);
                if ($retval_gunzip > 0) {
                    die(__('extractnotok52') . $file['description'] . "\n");
                } else {
                    echo $file['description'] . ' ' . __('extractok52') . "\n";
                }
            }
        } else {
            // Unable to extract the file correctly
            $error_message = __('message552') . "\n";
            $error_message .= __('message652');
            die($error_message);
        }

        // Apply MailWatch rights on files from the last run
        $mwUID =  exec('cat /etc/sudoers.d/mailwatch | grep "User_Alias MAILSCANNER" | sed "s/.*= \(.*\).*/\1/"', $output_cat, $retval_cat);
        if ($retval_cat > 0) {
            die(__('nofind52') . '.' . "\n");
        } else {
            $path = $extract_dir . 'GeoIP*.dat';
            passthru("chown $mwUID.$mwUID $path", $retval_chown);
            if ($retval_chown > 0) {
                die(__('nofindowner52') . ' ' . $extract_dir . '.' . "\n");
            }
        }

        echo __('processok52') . "\n\n";
        ob_flush();
        flush();
    } else {
        // Unable to read or write to the directory
        die(__('norread52') . ' ' . $extract_dir . ' ' . __('directory52') . ".\n");
    }
} else {
    $error_message = __('message752') . "\n";
    $error_message .= __('message852') . " $extract_dir" . '.';
    die($error_message);
}
