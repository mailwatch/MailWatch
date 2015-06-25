<?php

/*
 MailWatch for MailScanner
 Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 Copyright (C) 2014-2015  MailWatch Team (https://github.com/orgs/mailwatch/teams/team-stable)

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 In addition, as a special exception, the copyright holder gives permission to link the code of this program
 with those files in the PEAR library that are licensed under the PHP License (or with modified versions of those
 files that use the same license as those files), and distribute linked combinations including the two.
 You must obey the GNU General Public License in all respects for all of the code used other than those files in the
 PEAR library that are licensed under the PHP License. If you modify this program, you may extend this exception to
 your version of the program, but you are not obligated to do so.
 If you do not wish to do so, delete this exception statement from your version.

 As a special exception, you have permission to link this program with the JpGraph library and
 distribute executables, as long as you follow the requirements of the GNU GPL in regard to all of the software
 in the executable aside from JpGraph.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

//Require files
require_once('./functions.php');

// Authentication verification and keep the session alive
session_start();
require('./login.function.php');

html_start("GeoIP Database Update", 0, false, false);

if (!isset($_POST['run'])) {

    echo '<form method="POST" action="' . sanitizeInput($_SERVER['PHP_SELF']) . '">
	 <input type="hidden" name="run" value="true">
	 <table class="boxtable" width="100%">
            <tr><th>';
    echo __('updategeoip10');
    echo '</th></tr>
	    <tr>
	        <td>
                    <br>
	            This utility is used to download the GeoIP database files (which are updated on the first Tuesday of each month) from <a href="http://dev.maxmind.com/geoip/legacy/geolite/" target="_maxmind">MaxMind</a> which is used to work out the country of origin for any given IP address and is displayed on the Message Detail page.<br><br>
	        </td>
	    </tr>
	    <tr>
	        <td align="center"><br><input type="SUBMIT" value="Run Now"><br><br></td>
	    </tr>
	 </table>
	 </form>' . "\n";

} else {
    ob_start();
    echo "Downloading file, please wait....<br>\n";

    $ipv4_database_url = 'http://geolite.maxmind.com/download/geoip/database/GeoLiteCountry/GeoIP.dat.gz';
    $ipv6_database_url = 'http://geolite.maxmind.com/download/geoip/database/GeoIPv6.dat.gz';

    $ipv4_file = './temp/GeoIP.dat.gz';
    $ipv6_file = './temp/GeoIPv6.dat.gz';
    $extract_dir = './temp/';

    // Clean-up from last run
    if (file_exists($ipv4_file)) {
        unlink($ipv4_file);
    }
    if (file_exists($ipv6_file)) {
        unlink($ipv6_file);
    }

    ob_flush();

    if (!file_exists($ipv4_file) && !file_exists($ipv6_file)) {
        if (is_writable($extract_dir) && is_readable($extract_dir)) {
            if (extension_loaded('curl')) {
                $curl_generic_options = array(
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_BINARYTRANSFER => true,
                    CURLOPT_TIMEOUT => 180
                );
                if (USE_PROXY === true) {
                    $curl_proxy_options = array(
                        CURLOPT_PROXY => PROXY_SERVER,
                        CURLOPT_PROXYPORT => PROXY_PORT,
                        CURLOPT_PROXYTYPE => PROXY_TYPE
                    );
                    if (PROXY_USER != '') {
                        $curl_proxy_options[CURLOPT_PROXYUSERPWD] = PROXY_USER . ':' . PROXY_PASS;
                    }

                    $curl_generic_options = $curl_generic_options + $curl_proxy_options;
                }

                // IPv4 download
                $ch_ipv4 = curl_init();
                $fp_ipv4 = fopen($ipv4_file, "w+");
                $curl_ipv4_options = array(
                    CURLOPT_URL => $ipv4_database_url,
                    CURLOPT_FILE => $fp_ipv4,
                );
                curl_setopt_array($ch_ipv4, ($curl_generic_options + $curl_ipv4_options));
                if (false == curl_exec($ch_ipv4))
                {
                    die("Unable to download GeoIP ipv4 data file (CURL reported: ".curl_errno($ch_ipv4) .' ' . curl_error($ch_ipv4) . ").\n");
                } else {
                    curl_close($ch_ipv4);
                    fclose($fp_ipv4);
                    unset($fp_ipv4);
                }

                // IPv6 download
                $ch_ipv6 = curl_init();
                $fp_ipv6 = fopen($ipv6_file, "w+");
                $curl_ipv6_options = array(
                    CURLOPT_URL => $ipv6_database_url,
                    CURLOPT_FILE => $fp_ipv6,
                );
                curl_setopt_array($ch_ipv6, ($curl_generic_options + $curl_ipv6_options));
                if(false == curl_exec($ch_ipv6)) {
                    die("Unable to download GeoIP ipv6 data file (CURL reported: ".curl_errno($ch_ipv6) .' ' . curl_error($ch_ipv6) . ").\n");
                } else {
                    curl_close($ch_ipv6);
                    fclose($fp_ipv6);
                    unset($fp_ipv6);
                }
            } elseif (ini_get('allow_url_fopen')) {
                // try fopen
                $context = null;
                if (USE_PROXY) {
                    $context_options = array(
                        'http' => array(
                            'proxy' => 'tcp://' . PROXY_SERVER . ':' . PROXY_PORT,
                            'request_fulluri' => true,
                            'method' => 'GET'
                        )
                    );
                    if (PROXY_USER != '') {
                        $proxy_login_data = base64_encode(PROXY_USER . ':' . PROXY_PASS);
                        $context_options['http']['header'] = "Proxy-Authorization: Basic $proxy_login_data";
                    }
                    $context = stream_context_create($context_options);
                }

                file_put_contents($ipv4_file, fopen($ipv4_database_url, 'r', false, $context));
                file_put_contents($ipv6_file, fopen($ipv6_database_url, 'r', false, $context));
            } elseif (!in_array('exec', array_map('trim', explode(',', ini_get('disable_functions'))))) {
                //wget
                if (USE_PROXY) {
                    exec('wget -e use_proxy=on -e http_proxy='.PROXY_SERVER.':'.PROXY_PORT.' --proxy-user='.PROXY_USER.' --proxy-password='.PROXY_PASS.' -N ' . $ipv4_database_url . ' -O ' . $ipv4_file, $output_wget_ipv4, $retval_wget_ipv4);
                    exec('wget -e use_proxy=on -e http_proxy='.PROXY_SERVER.':'.PROXY_PORT.' --proxy-user='.PROXY_USER.' --proxy-password='.PROXY_PASS.' -N ' . $ipv6_database_url . ' -O ' . $ipv6_file, $output_wget_ipv6, $retval_wget_ipv6);
                } else {
                    exec('wget -N ' . $ipv4_database_url . ' -O ' . $ipv4_file, $output_wget_ipv4, $retval_wget_ipv4);
                    exec('wget -N ' . $ipv6_database_url . ' -O ' . $ipv6_file, $output_wget_ipv6, $retval_wget_ipv6);
                }
                if ($retval_wget_ipv4 > 0) {
                    die("Unable to download GeoIP ipv4 data file.\n");
                }
                if ($retval_wget_ipv6 > 0) {
                    die("Unable to download GeoIP ipv6 data file.\n");
                }
            } else {
                die("Unable to download GeoIP data file (tried CURL, fopen and wget).\n");
            }

            echo 'Download complete, unpacking files...<br>' . "\n";
            ob_flush();

            if (function_exists('gzopen')) {

                $zp_ipv4_gz = gzopen($ipv4_file, 'r');
                $targetFileipv4 = fopen(str_replace('.gz', '', $ipv4_file), 'wb');
                while ($string = gzread($zp_ipv4_gz, 4096)) {
                    fwrite($targetFileipv4, $string, strlen($string));
                }
                gzclose($zp_ipv4_gz);
                fclose($targetFileipv4);

                $zp_ipv6_gz = gzopen($ipv6_file, 'r');
                $targetFileipv6 = fopen(str_replace('.gz', '', $ipv6_file), 'wb');
                while ($string = gzread($zp_ipv6_gz, 4096)) {
                    fwrite($targetFileipv6, $string, strlen($string));
                }
                gzclose($zp_ipv6_gz);
                fclose($targetFileipv6);
            } elseif (!in_array('exec', array_map('trim', explode(',', ini_get('disable_functions'))))) {
                exec('gunzip -f ' . $ipv4_file, $output_gunzip_ipv4, $retval_gunzip_ipv4);
                exec('gunzip -f ' . $ipv6_file, $output_gunzip_ipv6, $retval_gunzip_ipv6);
                //TODO: add error handling
                if ($retval_gunzip_ipv4 > 0) {
                    die("Unable to extract GeoIP ipv4 data file.\n");
                }
                if ($retval_gunzip_ipv6 > 0) {
                    die("Unable to extract GeoIP ipv6 data file.\n");
                }
            } else {
                // unable to extract the file correctly
                die("Unable to extract GeoIP data file.\n");
            }

            echo 'Process completed!' . "\n";
            ob_flush();
            audit_log('Ran GeoIP update');
        } else {
            // unable to read or write to the directory
            die("Unable to read or write to the " . $extract_dir . " directory.\n");
        }
    } else {
        die("Files still exist for some reason\n");
    }
}

// Add the footer
html_end();
// close the connection to the Database
dbclose();
