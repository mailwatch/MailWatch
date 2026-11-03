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

/**
 * Download the GeoLite2 Country database from MaxMind and unpack it into $extract_dir.
 *
 * @param string $extract_dir directory that receives GeoLite2-Country.mmdb, with a trailing slash
 * @param string $eol         line ending of the progress messages
 *
 * @return true|string true on success, the error message otherwise
 */
function geoip_update_download($extract_dir, $eol)
{
    require_once __DIR__ . '/lib/request/Requests.php';
    Requests::register_autoloader();

    echo __('downfile15') . $eol;

    $files_base_url = 'https://download.maxmind.com';
    $file = [
        'description' => __('geoip15'),
        'path' => '/geoip/databases/GeoLite2-Country/download?suffix=tar.gz',
        'legacy_path' => '/app/geoip_download?edition_id=GeoLite2-Country&suffix=tar.gz&license_key=' . MAXMIND_LICENSE_KEY,
        'destination' => $extract_dir . 'GeoLite2-Country.tar.gz',
        'destinationFileName' => 'GeoLite2-Country.mmdb',
    ];

    // Clean-up from last run
    if (file_exists($file['destination'])) {
        unlink($file['destination']);
        @unlink(substr($file['destination'], 0, -3));
    }
    ob_flush();
    flush();

    if (file_exists($file['destination'])) {
        return __('message715') . $eol . __('message815') . ' ' . $extract_dir . '.';
    }

    if (!is_writable($extract_dir) || !is_readable($extract_dir)) {
        return __('norread15') . ' ' . $extract_dir . ' ' . __('directory15') . '.';
    }

    if (function_exists('fsockopen') || extension_loaded('curl')) {
        $requestSession = new Requests_Session($files_base_url . '/');
        $requestSession->options['useragent'] = 'MailWatch/' . mailwatch_version();

        if (USE_PROXY === true) {
            if (PROXY_USER !== '') {
                $requestSession->options['proxy']['authentication'] = [
                    PROXY_SERVER . ':' . PROXY_PORT,
                    PROXY_USER,
                    PROXY_PASS,
                ];
            } else {
                $requestSession->options['proxy']['authentication'] = [
                    PROXY_SERVER . ':' . PROXY_PORT,
                ];
            }

            switch (PROXY_TYPE) {
                case 'HTTP':
                case 'CURLPROXY_HTTP': // BC for old constant name
                    $requestSession->options['proxy']['type'] = 'HTTP';
                    break;
                case 'SOCKS5':
                case 'CURLPROXY_SOCKS5': // BC for old constant name
                    $requestSession->options['proxy']['type'] = 'SOCKS5';
                    break;
                default:
                    return __('dieproxy15');
            }
        }

        try {
            $requestSession->options['filename'] = $file['destination'];
            if (defined('MAXMIND_ACCOUNT_ID')) {
                // use basic auth
                $requestSession->options['auth'] = new Requests_Auth_Basic([MAXMIND_ACCOUNT_ID, MAXMIND_LICENSE_KEY]);

                // remove auth and hooks from redirect request
                $hooks = new Requests_Hooks();
                $hooks->register('requests.before_redirect', function(&$location, &$req_headers, &$req_data, &$options) {
                    $options['auth'] = false;
                    $options['hooks'] = new Requests_Hooks();
                });
                $requestSession->options['hooks'] = $hooks;
                $result = $requestSession->get($file['path']);
            } else {
                $result = $requestSession->get($file['legacy_path']);
            }
            $result->throw_for_status();
            if (true === $result->success) {
                echo $file['description'] . ' ' . __('downok15') . $eol;
            }
        } catch (Requests_Exception $e) {
            $error_message = sprintf('%s %s%s %s', __('downbad15'), $file['description'], __('colon99'), $e->getMessage());
            if (file_exists($file['destination'])) {
                $error_message .= sprintf(' (%s)', strip_tags(file_get_contents($file['destination'])));
            }

            return $error_message;
        }

        ob_flush();
        flush();

        echo __('downokunpack15') . $eol;
        ob_flush();
        flush();
    } elseif (!in_array('exec', array_map('trim', explode(',', ini_get('disable_functions'))), true)) {
        // wget, each argument quoted on its own
        $command = 'wget';
        if (USE_PROXY) {
            // the download is https, wget ignores http_proxy for it
            $proxy = PROXY_SERVER . ':' . PROXY_PORT;
            $command .= ' -e use_proxy=on -e ' . escapeshellarg('http_proxy=' . $proxy) . ' -e ' . escapeshellarg('https_proxy=' . $proxy);
            if (PROXY_USER !== '') {
                $command .= ' --proxy-user=' . escapeshellarg(PROXY_USER) . ' --proxy-password=' . escapeshellarg(PROXY_PASS);
            }
        }
        if (defined('MAXMIND_ACCOUNT_ID')) {
            $command .= ' --user=' . escapeshellarg(MAXMIND_ACCOUNT_ID) . ' --password=' . escapeshellarg(MAXMIND_LICENSE_KEY);
            $command .= ' ' . escapeshellarg($files_base_url . $file['path']);
        } else {
            $command .= ' ' . escapeshellarg($files_base_url . $file['legacy_path']);
        }
        $command .= ' -O ' . escapeshellarg($file['destination']);

        exec($command, $output_wget, $retval_wget);
        if ($retval_wget > 0) {
            return __('downbad15') . ' ' . $file['description'];
        }
        echo $file['description'] . ' ' . __('downok15') . $eol;
    } else {
        return __('message315') . $eol . __('message415');
    }

    // Extract files
    echo $eol;
    if (!class_exists('PharData')) {
        return __('message515') . $eol . __('message615');
    }
    $p = new PharData($file['destination']);
    $p->decompress();
    $phar = new PharData(substr($file['destination'], 0, -3));
    $phar->extractTo($extract_dir, null, true);
    echo $file['description'] . ' ' . __('unpackok15') . $eol;
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

    return true;
}
