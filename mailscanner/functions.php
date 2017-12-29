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

// Set error level (some distro's have php.ini set to E_ALL)
use MailWatch\Db;

error_reporting(E_ALL ^ E_DEPRECATED ^ E_STRICT);

$autoloader = require __DIR__ . '/../src/bootstrap.php';

// Read in MailWatch configuration file
if (!is_readable(__DIR__ . '/conf.php')) {
    die(__('cannot_read_conf'));
}
require_once __DIR__ . '/conf.php';


// more secure session cookies
ini_set('session.use_cookies', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.use_trans_sid', 0);

$session_cookie_secure = false;
if (SSL_ONLY === true) {
    ini_set('session.cookie_secure', 1);
    $session_cookie_secure = true;
}

//enforce session cookie security
$params = session_get_cookie_params();
if (defined('SESSION_NAME')) {
    session_name(SESSION_NAME);
}
session_set_cookie_params(0, $params['path'], $params['domain'], $session_cookie_secure, true);

// Load Language File
// If the translation file indicated at conf.php doesn´t exists, the system will load the English version.
if (!defined('LANG')) {
    define('LANG', 'en');
}
$langCode = LANG;
// If the user is allowed to select the language for the gui check which language he has choosen or create the cookie with the default lang
if (defined('USER_SELECTABLE_LANG')) {
    if (isset($_COOKIE['MW_LANG']) && checkLangCode($_COOKIE['MW_LANG'])) {
        $langCode = $_COOKIE['MW_LANG'];
    } else {
        setcookie('MW_LANG', LANG, 0, $params['path'], $params['domain'], $session_cookie_secure, false);
    }
}

// Load the lang file or en if the spicified language is not available
if (!is_file(__DIR__ . '/languages/' . $langCode . '.php')) {
    $lang = require __DIR__ . '/languages/en.php';
} else {
    $lang = require __DIR__ . '/languages/' . $langCode . '.php';
}

// Load the lang file or en if the spicified language is not available
if (!is_file(__DIR__ . '/languages/' . LANG . '.php')) {
    $systemLang = require __DIR__ . '/languages/en.php';
} else {
    $systemLang = require __DIR__ . '/languages/' . LANG . '.php';
}

$missingConfigEntries = checkConfVariables();
if ($missingConfigEntries['needed']['count'] !== 0) {
    $br = '';
    if (PHP_SAPI !== 'cli') {
        $br = '<br>';
    }
    echo __('missing_conf_entries') . $br . PHP_EOL;
    foreach ($missingConfigEntries['needed']['list'] as $missingConfigEntry) {
        echo '- ' . $missingConfigEntry . $br . PHP_EOL;
    }
    die();
}

// Set PHP path to use local PEAR modules only
set_include_path(
    '.' . PATH_SEPARATOR .
    MAILWATCH_HOME . '/lib/xmlrpc'
);

//Enforce SSL if SSL_ONLY=true
if (PHP_SAPI !== 'cli' && SSL_ONLY && !empty($_SERVER['PHP_SELF'])) {
    if (!isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'on') {
        header('Location: https://' . \MailWatch\Sanitize::sanitizeInput($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']));
        exit;
    }
}

//security headers
if (PHP_SAPI !== 'cli') {
    header('X-XSS-Protection: 1; mode=block');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    unset($session_cookie_secure);
    session_start();
}

// set default timezone
date_default_timezone_set(TIME_ZONE);



///////////////////////////////////////////////////////////////////////////////
// Functions
///////////////////////////////////////////////////////////////////////////////
/**
 * @return string
 */
function mailwatch_version()
{
    return '1.99.0-dev';
}

/**
 * @param $number
 * @return string
 */
function suppress_zeros($number)
{
    if (abs($number - 0.0) < 0.1) {
        return '.';
    }

    return $number;
}

function disableBrowserCache()
{
    header('Expires: Sat, 10 May 2003 00:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, M d Y H:i:s') . ' GMT');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Cache-Control: post-check=0, pre-check=0', false);
}


/**
 * @param string $sql
 * @param bool $printError
 * @return mysqli_result
 */
function dbquery($sql, $printError = true)
{
    $link = \MailWatch\Db::connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (DEBUG && headers_sent() && preg_match('/\bselect\b/i', $sql)) {
        dbquerydebug($link, $sql);
    }
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $result = $link->query($sql);

    if (true === $printError && false === $result) {
        // stop on query error
        $message = '<strong>Invalid query</strong>: ' . Db::$link->errno . ': ' . Db::$link->error . "<br>\n";
        $message .= '<strong>Whole query</strong>: <pre>' . $sql . '</pre>';
        die($message);
    }

    return $result;
}

/**
 * @param mysqli $link
 * @param string $sql
 */
function dbquerydebug($link, $sql)
{
    echo "<!--\n\n";
    $dbg_sql = 'EXPLAIN ' . $sql;
    echo "SQL:\n\n$sql\n\n";
    /** @var mysqli_result $result */
    $result = $link->query($dbg_sql);
    if ($result) {
        while ($row = $result->fetch_row()) {
            for ($f = 0; $f < $link->field_count; $f++) {
                echo $result->fetch_field_direct($f)->name . ': ' . $row[$f] . "\n";
            }
        }

        echo "\n-->\n\n";
        $result->free_result();
    } else {
        die(__('diedbquery03') . '(' . $link->connect_errno . ' ' . $link->connect_error . ')');
    }
}

/**
 * @param string $string
 * @param boolean $useSystemLang
 * @return string
 */
function __($string, $useSystemLang = false)
{
    if ($useSystemLang) {
        global $systemLang;
        $language = $systemLang;
    } else {
        global $lang;
        $language = $lang;
    }

    $debug_message = '';
    $pre_string = '';
    $post_string = '';
    if (DEBUG === true) {
        $debug_message = ' (' . $string . ')';
        $pre_string = '<span class="error">';
        $post_string = '</span>';
    }

    if (isset($language[$string])) {
        return $language[$string] . $debug_message;
    }

    $en_lang = require __DIR__ . DIRECTORY_SEPARATOR . 'languages' . DIRECTORY_SEPARATOR . 'en.php';
    if (isset($en_lang[$string])) {
        return $pre_string . $en_lang[$string] . $debug_message . $post_string;
    }

    return $pre_string . $language['i18_missing'] . $debug_message . $post_string;
}

/**
 * Returns true if $string is valid UTF-8 and false otherwise.
 *
 * @param  string $string
 * @return integer
 */
function is_utf8($string)
{
    // From http://w3.org/International/questions/qa-forms-utf-8.html
    return preg_match('%^(?:
          [\x09\x0A\x0D\x20-\x7E]            # ASCII
        | [\xC2-\xDF][\x80-\xBF]             # non-overlong 2-byte
        |  \xE0[\xA0-\xBF][\x80-\xBF]        # excluding overlongs
        | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}  # straight 3-byte
        |  \xED[\x80-\x9F][\x80-\xBF]        # excluding surrogates
        |  \xF0[\x90-\xBF][\x80-\xBF]{2}     # planes 1-3
        | [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15
        |  \xF4[\x80-\x8F][\x80-\xBF]{2}     # plane 16
    )*$%xs', $string);
}

/**
 * @param string $string
 * @return string
 */
function getUTF8String($string)
{
    if (function_exists('mb_check_encoding')) {
        if (!mb_check_encoding($string, 'UTF-8')) {
            $string = mb_convert_encoding($string, 'UTF-8');
        }
    } else {
        if (!is_utf8($string)) {
            $string = utf8_encode($string);
        }
    }

    return $string;
}

/**
 * @param string $header
 * @return string
 */
function getFROMheader($header)
{
    $sender = '';
    if (preg_match('/From:([ ]|\n)(.*(?=((\d{3}[A-Z]?[ ]+(\w|[-])+:.*)|(\s*\z))))/sUi', $header, $match) === 1) {
        if (isset($match[2])) {
            $sender = $match[2];
        }
        if (preg_match('/\S+@\S+/', $sender, $match_email) === 1 && isset($match_email[0])) {
            $sender = str_replace(['<', '>', '"'], '', $match_email[0]);
        }
    }
    return $sender;
}

/**
 * @param string $header
 * @return string
 */
function getSUBJECTheader($header)
{
    $subject = '';
    if (preg_match('/^\d{3}  Subject:([ ]|\n)(.*(?=((\d{3}[A-Z]?[ ]+(\w|[-])+:.*)|(\s*\z))))/iUsm', $header, $match) === 1) {
        $subLines = preg_split('/[\r\n]+/', $match[2]);
        for ($i = 0, $countSubLines = count($subLines); $i < $countSubLines; $i++) {
            $convLine = '';
            if (function_exists('imap_mime_header_decode')) {
                $linePartArr = imap_mime_header_decode($subLines[$i]);
                for ($j = 0, $countLinePartArr = count($linePartArr); $j < $countLinePartArr; $j++) {
                    if (strtolower($linePartArr[$j]->charset) === 'default') {
                        if ($linePartArr[$j]->text !== ' ') {
                            $convLine .= $linePartArr[$j]->text;
                        }
                    } else {
                        $textdecoded = @iconv(
                            strtoupper($linePartArr[$j]->charset),
                            'UTF-8//TRANSLIT//IGNORE',
                            $linePartArr[$j]->text
                        );
                        if (!$textdecoded) {
                            $convLine .= $linePartArr[$j]->text;
                        } else {
                            $convLine .= $textdecoded;
                        }
                    }
                }
            } else {
                $convLine .= str_replace('_', ' ', mb_decode_mimeheader($subLines[$i]));
            }
            $subject .= $convLine;
        }
    }

    return $subject;
}

/**
 * @param string $spamreport
 * @return string|false
 */
function sa_autolearn($spamreport)
{
    if (preg_match('/autolearn=spam/', $spamreport) === 1) {
        return __('saspam03');
    }

    if (preg_match('/autolearn=not spam/', $spamreport) === 1) {
        return __('sanotspam03');
    }

    return false;
}

/**
 * @param $spamreport
 * @return string
 */
function format_spam_report($spamreport)
{
    // Run regex against the MailScanner spamreport picking out the (score=xx, required x, RULES...)
    if (preg_match('/\s\((.+?)\)/i', $spamreport, $sa_rules)) {
        // Get rid of the first match from the array
        array_shift($sa_rules);
        // Split the array
        $sa_rules = explode(', ', $sa_rules[0]);
        // Check to make sure a check was actually run
        if ($sa_rules[0] === 'Message larger than max testing size' || $sa_rules[0] === 'timed out') {
            return $sa_rules[0];
        }

        // Get rid of the 'score=', 'required' and 'autolearn=' lines
        $notRulesLines = [
            //english
            'cached',
            'score=',
            'required',
            'autolearn=',
            //italian
            'punteggio=',
            'necessario',
            //german
            'benoetigt',
            'Wertung=',
            'gecached',
            //french
            'requis'
        ];
        array_walk($notRulesLines, function ($value) {
            return preg_quote($value, '/');
        });
        $notRulesLinesRegex = '(' . implode('|', $notRulesLines) . ')';

        $sa_rules = array_filter($sa_rules, function ($val) use ($notRulesLinesRegex) {
            return preg_match("/$notRulesLinesRegex/i", $val) === 0;
        });

        $output_array = [];
        foreach ($sa_rules as $sa_rule) {
            $output_array[] = get_sa_rule_desc($sa_rule);
        }

        // Return the result as an html formatted string
        if (count($output_array) > 0) {
            return '<table class="sa_rules_report" cellspacing="2" width="100%"><tr><th>' . __('score03') . '</th><th>' . __('matrule03') . '</th><th>' . __('description03') . '</th></tr>' . implode(
                    "\n",
                    $output_array
                ) . '</table>' . "\n";
        }

        return $spamreport;
    }

    // Regular expression did not match, return unmodified report instead
    return $spamreport;
}

/**
 * @param string $rule
 * @return string
 */
function get_sa_rule_desc($rule)
{
    // Check if SA scoring is enabled
    $rule_score = '';
    if (preg_match('/^(.+) (.+)$/', $rule, $regs)) {
        $rule = $regs[1];
        $rule_score = $regs[2];
    }
    $result = dbquery("SELECT rule, rule_desc FROM sa_rules WHERE rule='$rule'");
    $row = $result->fetch_object();
    if ($row && $row->rule && $row->rule_desc) {
        return ('<tr><td>' . $rule_score . '</td><td>' . $row->rule . '</td><td>' . $row->rule_desc . '</td></tr>' . "\n");
    }

    return "<tr><td>$rule_score</td><td>$rule</td><td>&nbsp;</td></tr>";
}

/**
 * @param string $rule
 * @return string|false
 */
function return_sa_rule_desc($rule)
{
    $result = dbquery("SELECT rule, rule_desc FROM sa_rules WHERE rule='$rule'");
    $row = $result->fetch_object();
    if ($row) {
        return htmlentities($row->rule_desc);
    }

    return false;
}

/**
 * @param string $mcpreport
 * @return mixed|string
 */
function format_mcp_report($mcpreport)
{
    // Clean-up input
    $mcpreport = preg_replace('/\n/', '', $mcpreport);
    $mcpreport = preg_replace('/\t/', ' ', $mcpreport);
    // Run regex against the MailScanner mcpreport picking out the (score=xx, required x, RULES...)
    if (preg_match('/ \((.+?)\)/i', $mcpreport, $sa_rules)) {
        // Get rid of the first match from the array
        array_shift($sa_rules);
        // Split the array
        $sa_rules = explode(', ', $sa_rules[0]);
        // Check to make sure a check was actually run
        if ($sa_rules[0] === 'Message larger than max testing size' || $sa_rules[0] === 'timed out') {
            return $sa_rules[0];
        }
        // Get rid of the 'score=', 'required' and 'autolearn=' lines
        foreach (['score=', 'required', 'autolearn='] as $val) {
            if (preg_match("/$val/", $sa_rules[0])) {
                array_shift($sa_rules);
            }
        }
        $output_array = [];
        foreach ($sa_rules as $val) {
            $output_array[] = get_mcp_rule_desc($val);
        }
        // Return the result as an html formatted string
        if (count($output_array) > 0) {
            return '<table class="sa_rules_report" cellspacing="2" width="100%">"."<tr><th>' . __('score03') . '</th><th>' . __('matrule03') . '</th><th>' . __('description03') . '</th></tr>' . implode(
                    "\n",
                    $output_array
                ) . '</table>' . "\n";
        }

        return $mcpreport;
    }

    // Regular expression did not match, return unmodified report instead
    return $mcpreport;
}

/**
 * @param $rule
 * @return string
 */
function get_mcp_rule_desc($rule)
{
    // Check if SA scoring is enabled
    $rule_score = '';
    if (preg_match('/^(.+) (.+)$/', $rule, $regs)) {
        list($rule, $rule_score) = $regs;
    }
    $result = dbquery("SELECT rule, rule_desc FROM mcp_rules WHERE rule='$rule'");
    $row = $result->fetch_object();
    if ($row && $row->rule && $row->rule_desc) {
        return ('<tr><td>' . $rule_score . '</td><td>' . $row->rule . '</td><td>' . $row->rule_desc . '</td></tr>' . "\n");
    }

    return '<tr><td>' . $rule_score . '<td>' . $rule . '</td><td>&nbsp;</td></tr>' . "\n";
}

/**
 * @param $rule
 * @return bool
 */
function return_mcp_rule_desc($rule)
{
    $result = dbquery("SELECT rule, rule_desc FROM mcp_rules WHERE rule='$rule'");
    $row = $result->fetch_object();
    if ($row) {
        return $row->rule_desc;
    }

    return false;
}

/**
 * @return string
 */
function return_todays_top_virus()
{
    if (\MailWatch\Antivirus::getVirusRegex() === null) {
        return __('unknownvirusscanner03');
    }
    $sql = '
SELECT
 report
FROM
 maillog
WHERE
 virusinfected>0
AND
 date = CURRENT_DATE()
';
    $result = dbquery($sql);
    $virus_array = [];
    while ($row = $result->fetch_object()) {
        $virus = \MailWatch\Antivirus::getVirus($row->report);
        if ($virus !== null) {
            $virus = \MailWatch\Antivirus::getVirusLink($virus);
            if (!isset($virus_array[$virus])) {
                $virus_array[$virus] = 1;
            } else {
                $virus_array[$virus]++;
            }
        }
    }
    if (count($virus_array) === 0) {
        return __('none03');
    }
    arsort($virus_array);
    reset($virus_array);

    // Get the topmost entry from the array
    $top = null;
    $count = 0;
    foreach ($virus_array as $key => $val) {
        if ($top === null) {
            $top = $val;
        } elseif ($val !== $top) {
            break;
        }
        $count++;
    }
    $topvirus_arraykeys = array_keys($virus_array);
    $topvirus = $topvirus_arraykeys[0];
    if ($count > 1) {
        // and ... others
        $topvirus .= sprintf(' ' . __('moretopviruses03'), $count - 1);
    }
    return $topvirus;
}

/**
 * @return array|mixed
 */
function get_disks()
{
    $disks = [];
    if (PHP_OS === 'Windows NT') {
        // windows
        $disks = `fsutil fsinfo drives`;
        $disks = str_word_count($disks, 1);
        //TODO: won't work on non english installation, we need to find an universal command
        if ($disks[0] !== 'Drives') {
            return [];
        }
        unset($disks[0]);
        foreach ($disks as $disk) {
            $disks[]['mountpoint'] = $disk . ':\\';
        }
    } else {
        // unix
        /*
         * Using /proc/mounts as it seem to be standard on unix
         *
         * http://unix.stackexchange.com/a/24230/33366
         * http://unix.stackexchange.com/a/12086/33366
         */
        $temp_drive = [];
        if (is_file('/proc/mounts')) {
            $mounted_fs = file('/proc/mounts');
            foreach ($mounted_fs as $fs_row) {
                $drive = preg_split("/[\s]+/", $fs_row);
                if ((substr($drive[0], 0, 5) === '/dev/') && (stripos($drive[1], '/chroot/') === false)) {
                    $temp_drive['device'] = $drive[0];
                    $temp_drive['mountpoint'] = $drive[1];
                    $disks[] = $temp_drive;
                    unset($temp_drive);
                }
                // TODO: list nfs mount (and other relevant fs type) in $disks[]
            }
        } else {
            // fallback to mount command
            $data = `mount`;
            $data = explode("\n", $data);
            foreach ($data as $disk) {
                $drive = preg_split("/[\s]+/", $disk);
                if ((substr($drive[0], 0, 5) === '/dev/') && (stripos($drive[2], '/chroot/') === false)) {
                    $temp_drive['device'] = $drive[0];
                    $temp_drive['mountpoint'] = $drive[2];
                    $disks[] = $temp_drive;
                    unset($temp_drive);
                }
            }
        }
    }

    return $disks;
}

/**
 * @param double $size
 * @param int $precision
 * @return string
 */
function formatSize($size, $precision = 2)
{
    if ($size === null) {
        return 'n/a';
    }
    if ($size === 0 || $size === '0') {
        return '0';
    }
    $base = log($size) / log(1024);
    $suffixes = ['B', 'kB', 'MB', 'GB', 'TB', 'PB'];

    return round(1024 ** ($base - floor($base)), $precision) . $suffixes[(int)floor($base)];
}

/**
 * @param $data_in
 * @param $info_out
 */
function format_report_volume(&$data_in, &$info_out)
{
    // Measures
    $kb = 1024;
    $mb = 1024 * $kb;
    $gb = 1024 * $mb;
    $tb = 1024 * $gb;

    // Copy the data to a temporary variable
    $temp = $data_in;

    // Work out the average size of values in the array
    $count = count($temp);
    $sum = array_sum($temp);
    $average = $sum / $count;

    // Work out the largest value in the array
    arsort($temp);
    array_pop($temp);

    // Calculate the correct display size for the average value
    if ($average < $kb) {
        $info_out['formula'] = 1;
        $info_out['shortdesc'] = 'b';
        $info_out['longdesc'] = 'Bytes';
    } else {
        if ($average < $mb) {
            $info_out['formula'] = $kb;
            $info_out['shortdesc'] = 'Kb';
            $info_out['longdesc'] = 'Kilobytes';
        } else {
            if ($average < $gb) {
                $info_out['formula'] = $mb;
                $info_out['shortdesc'] = 'Mb';
                $info_out['longdesc'] = 'Megabytes';
            } else {
                if ($average < $tb) {
                    $info_out['formula'] = $gb;
                    $info_out['shortdesc'] = 'Gb';
                    $info_out['longdesc'] = 'Gigabytes';
                } else {
                    $info_out['formula'] = $tb;
                    $info_out['shortdesc'] = 'Tb';
                    $info_out['longdesc'] = 'Terabytes';
                }
            }
        }
    }

    // Modify the original data accordingly
    $num_data_in = count($data_in);
    for ($i = 0; $i < $num_data_in; $i++) {
        $data_in[$i] /= $info_out['formula'];
    }
}

/**
 * @param $input
 * @param $maxlen
 * @return string
 */
function trim_output($input, $maxlen)
{
    if ($maxlen > 0 && strlen($input) >= $maxlen) {
        return substr($input, 0, $maxlen) . '...';
    }

    return $input;
}





/**
 * @param $date
 * @param string $format
 * @return mixed|string
 */
function translateQuarantineDate($date, $format = 'dmy')
{
    $y = substr($date, 0, 4);
    $m = substr($date, 4, 2);
    $d = substr($date, 6, 2);

    $format = strtolower($format);

    switch ($format) {
        case 'dmy':
            return "$d/$m/$y";
        case 'sql':
            return "$y-$m-$d";
        default:
            $format = preg_replace('/%y/', $y, $format);
            $format = preg_replace('/%m/', $m, $format);
            $format = preg_replace('/%d/', $d, $format);

            return $format;
    }
}

/**
 * @param $preserve
 * @return string|false
 */
function subtract_get_vars($preserve)
{
    if (is_array($_GET)) {
        $output = [];
        foreach ($_GET as $k => $v) {
            if (strtolower($k) !== strtolower($preserve)) {
                $output[] = "$k=$v";
            }
        }
        if (count($output) > 0) {
            $output = implode('&amp;', $output);
            return '&amp;' . $output;
        }

        return false;
    }

    return false;
}

/**
 * @param string[] $preserve
 * @return string|false
 */
function subtract_multi_get_vars($preserve)
{
    if (is_array($_GET)) {
        $output = [];
        foreach ($_GET as $k => $v) {
            if (!in_array($k, $preserve, true)) {
                $output[] = "$k=$v";
            }
        }
        if (count($output) > 0) {
            $output = implode('&amp;', $output);
            return '&amp;' . $output;
        }
    }

    return false;
}

/**
 * @param string $sql the sql query for which the page will be created
 * @return int
 */
function generatePager($sql)
{
    if (isset($_GET['offset'])) {
        $from = (int)$_GET['offset'];
    } else {
        $from = 0;
    }

    // Remove any ORDER BY clauses as this will slow the count considerably
    if ($pos = strpos($sql, 'ORDER BY')) {
        $sqlcount = substr($sql, 0, $pos);
    } else {
        $sqlcount = $sql;
    }

    // Count the number of rows that would be returned by the query
    $sqlcount = 'SELECT COUNT(*) ' . strstr($sqlcount, 'FROM');
    $results = dbquery($sqlcount);
    $rows = Db::mysqli_result($results, 0);

    // Build the pager data
    $pager_options = [
        'mode' => 'Sliding',
        'perPage' => MAX_RESULTS,
        'delta' => 2,
        'totalItems' => $rows,
    ];
    $pager = Pager::factory($pager_options);

    //then we fetch the relevant records for the current page
    list($from, $to) = $pager->getOffsetByPageId();

    echo '<table cellspacing="1" class="mail" >
<tr>
<th colspan="5">' . __('disppage03') . ' ' . $pager->getCurrentPageID() . ' ' . __('of03') . ' ' . $pager->numPages() . ' - ' . __('records03') . ' ' . $from . ' ' . __('to0203') . ' ' . $to . ' ' . __('of03') . ' ' . $pager->numItems() . '</th>
</tr>
<tr>
<td align="center">' . "\n";
    //show the links
    echo $pager->links;
    echo '</td>
            </tr>
      </table>
</tr>
<tr>
  <td colspan="' . ($_SESSION['user_type'] === 'A' ? '5' : '4') . '">';

    return $from;
}

/**
 * @param $sql
 * @param bool|string $table_heading
 * @param bool $pager
 * @param bool $order
 * @param bool $operations
 */
function db_colorised_table($sql, $table_heading = false, $pager = false, $order = false, $operations = false)
{
    // Ordering
    $orderby = null;
    $orderdir = '';
    if (isset($_GET['orderby'])) {
        $orderby = \MailWatch\Sanitize::sanitizeInput($_GET['orderby']);
        switch (strtoupper($_GET['orderdir'])) {
            case 'A':
                $orderdir = 'ASC';
                break;
            case 'D':
                $orderdir = 'DESC';
                break;
        }
    }
    if (!empty($orderby)) {
        if (($p = stristr($sql, 'ORDER BY')) !== false) {
            // We already have an existing ORDER BY clause
            $p = "ORDER BY\n  " . $orderby . ' ' . $orderdir . ',' . substr($p, strlen('ORDER BY') + 2);
            $sql = substr($sql, 0, strpos($sql, 'ORDER BY')) . $p;
        } else {
            // No existing ORDER BY - disable feature
            $order = false;
        }
    }

    if ($pager) {
        $from = generatePager($sql);

        // Re-run the original query and limit the rows
        $limit = $from - 1;
        $sql .= " LIMIT $limit," . MAX_RESULTS;
        $sth = dbquery($sql);
        $rows = $sth->num_rows;
        $fields = $sth->field_count;
        // Account for extra operations column
        if ($operations !== false) {
            $fields++;
        }
    } else {
        $sth = dbquery($sql);
        $rows = $sth->num_rows;
        $fields = $sth->field_count;
        // Account for extra operations column
        if ($operations !== false) {
            $fields++;
        }
    }

    if ($rows > 0) {
        if ($operations !== false) {
            // Start form for operations
            echo '<form name="operations" action="./do_message_ops.php" method="POST">' . "\n";
            echo '<input type="hidden" name="token" value="' . $_SESSION['token'] . '">' . "\n";
            echo '<INPUT TYPE="HIDDEN" NAME="formtoken" VALUE="' . generateFormToken('/do_message_ops.php form token') . '">' . "\n";
        }
        \MailWatch\Html::printColorCodes();
        echo '<table cellspacing="1" width="100%" class="mail rowhover">' . "\n";
        // Work out which columns to display
        $display = [];
        $orderable = [];
        $fieldname = [];
        $align = [];
        for ($f = 0; $f < $fields; $f++) {
            if ($f === 0 && $operations !== false) {
                // Set up display for operations form elements
                $display[$f] = true;
                $orderable[$f] = false;
                // Set it up not to wrap - tricky way to leach onto the align field
                $align[$f] = 'center" style="white-space:nowrap';
                $fieldname[$f] = __('ops03') . '<br><a href="javascript:SetRadios(\'S\')">' . __('radiospam203') . '</a>&nbsp;&nbsp;&nbsp;<a href="javascript:SetRadios(\'H\')">' . __('radioham03') . '</a>&nbsp;&nbsp;&nbsp;<a href="javascript:SetRadios(\'F\')">' . __('radioforget03') . '</a>&nbsp;&nbsp;&nbsp;<a href="javascript:SetRadios(\'R\')">' . __('radiorelease03') . '</a>';
                continue;
            }
            $display[$f] = true;
            $orderable[$f] = true;
            $align[$f] = false;
            // Set up the mysql column to account for operations
            $colnum = $f;
            if ($operations !== false) {
                $colnum = $f - 1;
            }

            $fieldInfo = $sth->fetch_field_direct($colnum);
            switch ($fieldname[$f] = $fieldInfo->name) {
                case 'host':
                    $fieldname[$f] = 'Host';
                    if (DISTRIBUTED_SETUP) {
                        $display[$f] = true;
                    } else {
                        $display[$f] = false;
                    }
                    break;
                case 'timestamp':
                    $fieldname[$f] = __('datetime03');
                    $align[$f] = 'center';
                    break;
                case 'datetime':
                    $fieldname[$f] = __('datetime03');
                    $align[$f] = 'center';
                    break;
                case 'id':
                    $fieldname[$f] = 'ID';
                    $orderable[$f] = false;
                    $align[$f] = 'center';
                    break;
                case 'id2':
                    $fieldname[$f] = '#';
                    $orderable[$f] = false;
                    $align[$f] = 'center';
                    break;
                case 'size':
                    $fieldname[$f] = __('size03');
                    $align[$f] = 'right';
                    break;
                case 'from_address':
                    $fieldname[$f] = __('from03');
                    break;
                case 'to_address':
                    $fieldname[$f] = __('to03');
                    break;
                case 'subject':
                    $fieldname[$f] = __('subject03');
                    break;
                case 'clientip':
                    if (defined('DISPLAY_IP') && DISPLAY_IP) {
                        $fieldname[$f] = __('clientip03');
                    }
                    $display[$f] = true;
                    break;
                case 'archive':
                    $display[$f] = false;
                    break;
                case 'isspam':
                    $display[$f] = false;
                    break;
                case 'ishighspam':
                    $display[$f] = false;
                    break;
                case 'issaspam':
                    $display[$f] = false;
                    break;
                case 'isrblspam':
                    $display[$f] = false;
                    break;
                case 'spamwhitelisted':
                    $display[$f] = false;
                    break;
                case 'spamblacklisted':
                    $display[$f] = false;
                    break;
                case 'spamreport':
                    $display[$f] = false;
                    break;
                case 'virusinfected':
                    $display[$f] = false;
                    break;
                case 'nameinfected':
                    $display[$f] = false;
                    break;
                case 'otherinfected':
                    $display[$f] = false;
                    break;
                case 'report':
                    $display[$f] = false;
                    break;
                case 'ismcp':
                    $display[$f] = false;
                    break;
                case 'ishighmcp':
                    $display[$f] = false;
                    break;
                case 'issamcp':
                    $display[$f] = false;
                    break;
                case 'mcpwhitelisted':
                    $display[$f] = false;
                    break;
                case 'mcpblacklisted':
                    $display[$f] = false;
                    break;
                case 'mcpreport':
                    $display[$f] = false;
                    break;
                case 'hostname':
                    $fieldname[$f] = __('host03');
                    $display[$f] = true;
                    break;
                case 'date':
                    $fieldname[$f] = __('date03');
                    break;
                case 'time':
                    $fieldname[$f] = __('time03');
                    break;
                case 'headers':
                    $display[$f] = false;
                    break;
                case 'sascore':
                    if (true === \MailWatch\MailScanner::getConfTrueFalse('UseSpamAssassin')) {
                        $fieldname[$f] = __('sascore03');
                        $align[$f] = 'right';
                    } else {
                        $display[$f] = false;
                    }
                    break;
                case 'mcpsascore':
                    if (\MailWatch\MailScanner::getConfTrueFalse('MCPChecks')) {
                        $fieldname[$f] = __('mcpscore03');
                        $align[$f] = 'right';
                    } else {
                        $display[$f] = false;
                    }
                    break;
                case 'status':
                    $fieldname[$f] = __('status03');
                    $orderable[$f] = false;
                    break;
                case 'message':
                    $fieldname[$f] = __('message03');
                    break;
                case 'attempts':
                    $fieldname[$f] = __('tries03');
                    $align[$f] = 'right';
                    break;
                case 'lastattempt':
                    $fieldname[$f] = __('last03');
                    $align[$f] = 'right';
                    break;
                case 'released':
                    $display[$f] = false;
                    break;
                case 'salearn':
                    $display[$f] = false;
                    break;
            }
        }
        // Table heading
        if (isset($table_heading) && $table_heading !== '') {
            // Work out how many columns are going to be displayed
            $column_headings = 0;
            for ($f = 0; $f < $fields; $f++) {
                if ($display[$f]) {
                    $column_headings++;
                }
            }
            echo ' <tr class="nohover"">' . "\n";
            echo '  <th colspan="' . $column_headings . '">' . $table_heading . '</th>' . "\n";
            echo ' </tr>' . "\n";
        }
        // Column headings
        echo '<tr class="sonoqui nohover">' . "\n";
        for ($f = 0; $f < $fields; $f++) {
            if ($display[$f]) {
                if ($order && $orderable[$f]) {
                    // Set up the mysql column to account for operations
                    if ($operations !== false) {
                        $colnum = $f - 1;
                    } else {
                        $colnum = $f;
                    }
                    $fieldInfo = $sth->fetch_field_direct($colnum);
                    echo "  <th>\n";
                    echo "  $fieldname[$f] (<a href=\"?orderby=" . $fieldInfo->name
                        . '&amp;orderdir=a' . subtract_multi_get_vars(
                            ['orderby', 'orderdir']
                        ) . '">A</a>/<a href="?orderby=' . $fieldInfo->name
                        . '&amp;orderdir=d' . subtract_multi_get_vars(['orderby', 'orderdir']) . "\">D</a>)\n";
                    echo "  </th>\n";
                } else {
                    echo '  <th>' . $fieldname[$f] . '</th>' . "\n";
                }
            }
        }
        echo ' </tr>' . "\n";
        // Rows
        $id = '';
        $jsRadioCheck = '';
        $jsReleaseCheck = '';
        for ($r = 0; $r < $rows; $r++) {
            $row = $sth->fetch_row();
            if ($operations !== false) {
                // Prepend operations elements - later on, replace REPLACEME w/ message id
                array_unshift(
                    $row,
                    '<input name="OPT-REPLACEME" type="RADIO" value="S">&nbsp;<input name="OPT-REPLACEME" type="RADIO" value="H">&nbsp;<input name="OPT-REPLACEME" type="RADIO" value="F">&nbsp;<input name="OPTRELEASE-REPLACEME" type="checkbox" value="R">'
                );
            }
            // Work out field colourings and modify the incoming data as necessary
            // and populate the generate an overall 'status' for the mail.
            $status_array = [];
            $infected = false;
            $highspam = false;
            $spam = false;
            $whitelisted = false;
            $blacklisted = false;
            $mcp = false;
            $highmcp = false;
            $released = false;
            $salearnham = false;
            $salearnspam = false;
            for ($f = 0; $f < $fields; $f++) {
                if ($operations !== false) {
                    if ($f === 0) {
                        // Skip the first field if it is operations
                        continue;
                    }
                    $fieldNumber = $f - 1;
                } else {
                    $fieldNumber = $f;
                }
                $field = $sth->fetch_field_direct($fieldNumber);
                switch ($field->name) {
                    case 'id':
                        // Store the id for later use
                        $id = $row[$f];
                        // Create a link to detail.php
                        $row[$f] = '<a href="detail.php?token=' . $_SESSION['token'] . '&amp;id=' . $row[$f] . '">' . $row[$f] . '</a>' . "\n";
                        break;
                    case 'id2':
                        // Store the id for later use
                        $id = $row[$f];
                        // Create a link to detail.php as [<link>]
                        $row[$f] = '<a href="detail.php?token=' . $_SESSION['token'] . "&amp;id=$row[$f]\" ><i class=\"mw-icon mw-info-circle\" aria-hidden=\"true\"></i></a>";
                        break;
                    case 'from_address':
                        $row[$f] = htmlentities($row[$f]);
                        if (FROMTO_MAXLEN > 0) {
                            $row[$f] = trim_output($row[$f], FROMTO_MAXLEN);
                        }
                        break;
                    case 'clientip':
                        $clientip = $row[$f];
                        if (defined('RESOLVE_IP_ON_DISPLAY') && RESOLVE_IP_ON_DISPLAY === true) {
                            if (ip_in_range($clientip)) {
                                $host = 'Internal Network';
                            } elseif (($host = gethostbyaddr($clientip)) === $clientip) {
                                $host = 'Unknown';
                            }
                            $row[$f] .= " ($host)";
                        }
                        break;
                    case 'to_address':
                        $row[$f] = htmlentities($row[$f]);
                        if (FROMTO_MAXLEN > 0) {
                            // Trim each address to specified size
                            $to_temp = explode(',', $row[$f]);
                            $num_to_temp = count($to_temp);
                            for ($t = 0; $t < $num_to_temp; $t++) {
                                $to_temp[$t] = trim_output($to_temp[$t], FROMTO_MAXLEN);
                            }
                            // Return the data
                            $row[$f] = implode(',', $to_temp);
                        }
                        // Put each address on a new line
                        $row[$f] = str_replace(',', '<br>', $row[$f]);
                        break;
                    case 'subject':
                        $row[$f] = htmlspecialchars(getUTF8String(decode_header($row[$f])));
                        if (SUBJECT_MAXLEN > 0) {
                            $row[$f] = trim_output($row[$f], SUBJECT_MAXLEN);
                        }
                        break;
                    case 'isspam':
                        if ($row[$f] === 'Y' || $row[$f] > 0) {
                            $spam = true;
                            $status_array[] = __('spam103');
                        }
                        break;
                    case 'ishighspam':
                        if ($row[$f] === 'Y' || $row[$f] > 0) {
                            $highspam = true;
                        }
                        break;
                    case 'ismcp':
                        if ($row[$f] === 'Y' || $row[$f] > 0) {
                            $mcp = true;
                            $status_array[] = __('mcp03');
                        }
                        break;
                    case 'ishighmcp':
                        if ($row[$f] === 'Y' || $row[$f] > 0) {
                            $highmcp = true;
                        }
                        break;
                    case 'virusinfected':
                        if ($row[$f] === 'Y' || $row[$f] > 0) {
                            $infected = true;
                            $status_array[] = __('virus03');
                        }
                        break;
                    case 'report':
                        // IMPORTANT NOTE: for this to work correctly the 'report' field MUST
                        // appear after the 'virusinfected' field within the SQL statement.
                        $virus = \MailWatch\Antivirus::getVirus($row[$f]);
                        if (defined('DISPLAY_VIRUS_REPORT') && DISPLAY_VIRUS_REPORT === true && $virus !== null) {
                            foreach ($status_array as $k => $v) {
                                if ($v = str_replace('Virus', 'Virus (' . \MailWatch\Antivirus::getVirusLink($virus) . ')', $v)) {
                                    $status_array[$k] = $v;
                                }
                            }
                        }
                        break;
                    case 'nameinfected':
                        if ($row[$f] === 'Y' || $row[$f] > 0) {
                            $infected = true;
                            $status_array[] = __('badcontent03');
                        }
                        break;
                    case 'otherinfected':
                        if ($row[$f] === 'Y' || $row[$f] > 0) {
                            $infected = true;
                            $status_array[] = __('otherinfected03');
                        }
                        break;
                    case 'size':
                        $row[$f] = formatSize($row[$f]);
                        break;
                    case 'spamwhitelisted':
                        if ($row[$f] === 'Y' || $row[$f] > 0) {
                            $whitelisted = true;
                            $status_array[] = __('whitelisted03');
                        }
                        break;
                    case 'spamblacklisted':
                        if ($row[$f] === 'Y' || $row[$f] > 0) {
                            $blacklisted = true;
                            $status_array[] = __('blacklisted03');
                        }
                        break;
                    case 'clienthost':
                        $hostname = gethostbyaddr($row[$f]);
                        if ($hostname === $row[$f]) {
                            $row[$f] = __('hostfailed03');
                        } else {
                            $row[$f] = $hostname;
                        }
                        break;
                    case 'released':
                        if ($row[$f] > 0) {
                            $released = true;
                            $status_array[] = __('released03');
                        }
                        break;
                    case 'salearn':
                        switch ($row[$f]) {
                            case 1:
                                $salearnham = true;
                                $status_array[] = __('learnham03');
                                break;
                            case 2:
                                $salearnspam = true;
                                $status_array[] = __('learnspam03');
                                break;
                        }
                        break;
                    case 'status':
                        // NOTE: this should always be the last row for it to be displayed correctly
                        // Work out status
                        if (count($status_array) === 0) {
                            $status = __('clean03');
                        } else {
                            $status = '';
                            foreach ($status_array as $item) {
                                if ($item === __('released03')) {
                                    $class = 'released';
                                } elseif ($item === __('learnham03')) {
                                    $class = 'salearn-1';
                                } elseif ($item === __('learnspam03')) {
                                    $class = 'salearn-2';
                                } else {
                                    $class = '';
                                }
                                $status .= '<div class="' . $class . '">' . $item . '</div>';
                            }
                        }
                        $row[$f] = $status;
                        break;
                }
            }
            // Now add the id to the operations form elements
            if ($operations !== false) {
                $row[0] = str_replace('REPLACEME', $id, $row[0]);
                $jsRadioCheck .= "  document.operations.elements[\"OPT-$id\"][val].checked = true;\n";
                $jsReleaseCheck .= "  document.operations.elements[\"OPTRELEASE-$id\"].checked = true;\n";
            }
            // Colorise the row
            switch (true) {
                case $infected:
                    echo '<tr class="infected">' . "\n";
                    break;
                case $whitelisted:
                    echo '<tr class="whitelisted">' . "\n";
                    break;
                case $blacklisted:
                    echo '<tr class="blacklisted">' . "\n";
                    break;
                case $highspam:
                    echo '<tr class="highspam">' . "\n";
                    break;
                case $spam:
                    echo '<tr class="spam">' . "\n";
                    break;
                case $highmcp:
                    echo '<tr class="highmcp">' . "\n";
                    break;
                case $mcp:
                    echo '<tr class="mcp">' . "\n";
                    break;
                default:
                    if (isset($fieldname['mcpsascore']) && $fieldname['mcpsascore'] !== '') {
                        echo '<tr class="mcp">' . "\n";
                    } else {
                        echo '<tr >' . "\n";
                    }
                    break;
            }
            // Display the rows
            for ($f = 0; $f < $fields; $f++) {
                if ($display[$f]) {
                    if ($align[$f]) {
                        if ($f === 0) {
                            echo ' <td align="' . $align[$f] . '" class="link-transparent">' . $row[$f] . '</td>' . "\n";
                        } else {
                            echo ' <td align="' . $align[$f] . '">' . $row[$f] . '</td>' . "\n";
                        }
                    } else {
                        echo ' <td>' . $row[$f] . '</td>' . "\n";
                    }
                }
            }
            echo ' </tr>' . "\n";
        }
        echo '</table>' . "\n";
        // Javascript function to clear radio buttons
        if ($operations !== false) {
            echo "
<script type='text/javascript'>
    function ClearRadios() {
        var e=document.operations.elements;
        for(i=0; i<e.length; i++) {
            if (e[i].type=='radio' || e[i].type=='checkbox') {
                e[i].checked=false;
            }
        }
    }

    function SetRadios(p) {
        var val;
        var values = {
            'S'  : 0,
            'H'  : 1,
            'F'  : 2,
            'R'  : 3
        };
        switch (p) {
            case 'S':
            case 'H':
            case 'F':
                val = values[p];
                $jsRadioCheck
                break;
            case 'R':
                $jsReleaseCheck
                break;
            case 'C':
                ClearRadios();
                break;
            default:
                return;
        }
    }
</script>
   <p>&nbsp; <a href=\"javascript:SetRadios('S')\">" . __('radiospam203') . "</a>
   &nbsp; <a href=\"javascript:SetRadios('H')\">" . __('radioham03') . "</a>
   &nbsp; <a href=\"javascript:SetRadios('F')\">" . __('radioforget03') . "</a>
   &nbsp; <a href=\"javascript:SetRadios('R')\">" . __('radiorelease03') . '</a>
   &nbsp; ' . __('or03') . " <a href=\"javascript:SetRadios('C')\">" . __('clear03') . "</p>
   <p><input type='SUBMIT' name='SUBMIT' value='" . __('learn03') . "'></p>
   </form>
   <p><b>" . __('spam203') . ' &nbsp; <b>' . __('ham03') . ' &nbsp; <b>' . __('forget03') . ' &nbsp; <b>' . __('release03') . '' . "\n";
        }
        echo '<br>' . "\n";
        if ($pager) {
            generatePager($sql);
        }
    }
}

/**
 * Function to display data as a table
 *
 * @param $sql
 * @param string|null $title
 * @param bool|false $pager
 * @param bool|false $operations
 */
function dbtable($sql, $title = null, $pager = false, $operations = false)
{
    /*
    // Query the data
    $sth = dbquery($sql);

    // Count the number of rows in a table
    $rows = $sth->num_rows;

    // Count the nubmer of fields
    $fields = $sth->field_count;
    */

    // Turn on paging of for the database
    if ($pager) {
        $from = 0;
        if (isset($_GET['offset'])) {
            $from = (int)$_GET['offset'];
        }

        // Remove any ORDER BY clauses as this will slow the count considerably
        if ($pos = strpos($sql, 'ORDER BY')) {
            $sqlcount = substr($sql, 0, $pos);
        } else {
            $sqlcount = $sql;
        }

        // Count the number of rows that would be returned by the query
        $sqlcount = 'SELECT COUNT(*) AS numrows ' . strstr($sqlcount, 'FROM');

        $results = dbquery($sqlcount);
        $resultsFirstRow = $results->fetch_array();
        $rows = (int)$resultsFirstRow['numrows'];

        // Build the pager data
        $pager_options = [
            'mode' => 'Sliding',
            'perPage' => MAX_RESULTS,
            'delta' => 2,
            'totalItems' => $rows,
        ];
        $pager = Pager::factory($pager_options);

        //then we fetch the relevant records for the current page
        list($from, $to) = $pager->getOffsetByPageId();

        echo '<table cellspacing="1" class="mail" >
    <tr>
   <th colspan="5">' . __('disppage03') . ' ' . $pager->getCurrentPageID() . ' ' . __('of03') . ' ' . $pager->numPages() . ' - ' . __('records03') . ' ' . $from . ' ' . __('to0203') . ' ' . $to . ' ' . __('of03') . ' ' . $pager->numItems() . '</th>
  </tr>
  <tr>
  <td align="center">' . "\n";
        //show the links
        echo $pager->links;
        echo '</td>
                </tr>
          </table>
</tr>
<tr>
  <td colspan="' . ($_SESSION['user_type'] === 'A' ? '5' : '4') . '">';

        // Re-run the original query and limit the rows
        $sql .= ' LIMIT ' . ($from - 1) . ',' . MAX_RESULTS;
        $sth = dbquery($sql);
        $rows = $sth->num_rows;
        $fields = $sth->field_count;
        // Account for extra operations column
        if ($operations !== false) {
            $fields++;
        }
    } else {
        $sth = dbquery($sql);
        $rows = $sth->num_rows;
        $fields = $sth->field_count;
        // Account for extra operations column
        if ($operations !== false) {
            $fields++;
        }
    }

    if ($rows > 0) {
        echo '<table cellspacing="1" width="100%" class="mail">' . "\n";
        if ($title !== null) {
            echo '<tr><th colspan=' . $fields . '>' . $title . '</TH></tr>' . "\n";
        }
        // Column headings
        echo ' <tr>' . "\n";
        if ($operations !== false) {
            echo '<td></td>';
        }

        foreach ($sth->fetch_fields() as $field) {
            echo '  <th>' . $field->name . '</th>' . "\n";
        }
        echo ' </tr>' . "\n";
        // Rows
        while ($row = $sth->fetch_row()) {
            echo ' <tr class="table-background">' . "\n";
            for ($f = 0; $f < $fields; $f++) {
                echo '  <td>' . preg_replace(
                        "/,([^\s])/",
                        ', $1',
                        $row[$f]
                    ) . '</td>' . "\n";
            }
            echo ' </tr>' . "\n";
        }
        echo '</table>' . "\n";
    } else {
        echo __('norowfound03') . "\n";
    }
    echo '<br>' . "\n";
    if ($pager) {
        $from = 0;
        if (isset($_GET['offset'])) {
            $from = (int)$_GET['offset'];
        }

        // Remove any ORDER BY clauses as this will slow the count considerably
        $sqlcount = '';
        if ($pos = strpos($sql, 'ORDER BY')) {
            $sqlcount = substr($sql, 0, $pos);
        }

        // Count the number of rows that would be returned by the query
        $sqlcount = 'SELECT COUNT(*) ' . strstr($sqlcount, 'FROM');
        $rows = Db::mysqli_result(dbquery($sqlcount), 0);

        // Build the pager data
        $pager_options = [
            'mode' => 'Sliding',
            'perPage' => MAX_RESULTS,
            'delta' => 2,
            'totalItems' => $rows,
        ];
        $pager = Pager::factory($pager_options);

        //then we fetch the relevant records for the current page
        list($from, $to) = $pager->getOffsetByPageId();

        echo '<table cellspacing="1" class="mail" >
    <tr>
   <th colspan="5">' . __('disppage03') . ' ' . $pager->getCurrentPageID() . ' ' . __('of03') . ' ' . $pager->numPages() . ' - ' . __('records03') . ' ' . $from . ' ' . __('to0203') . ' ' . $to . ' ' . __('of03') . ' ' . $pager->numItems() . '</th>
  </tr>
  <tr>
  <td align="center">' . "\n";
        //show the links
        echo $pager->links;
        echo '</td>
                </tr>
          </table>
</tr>
<tr>
  <td colspan="' . ($_SESSION['user_type'] === 'A' ? '5' : '4') . '">';
    }
}

/**
 * @param $sql

function db_vertical_table($sql)
 * {
 * $sth = dbquery($sql);
 * $rows = $sth->num_rows;
 * $fields = $sth->field_count;
 *
 * if ($rows > 0) {
 * echo '<table border="1" class="mail">' . "\n";
 * while ($row = $sth->fetch_row()) {
 * for ($f = 0; $f < $fields; $f++) {
 * $fieldInfo = $sth->fetch_field_direct($f);
 * echo " <tr>\n";
 * echo "  <td>" . $fieldInfo->name . "</td>\n";
 * echo "  <td>" . $row[$f] . "</td>\n";
 * echo " </tr>\n";
 * }
 * }
 * echo "</table>\n";
 * } else {
 * echo "No rows retrieved\n";
 * }
 * }
 */

/**
 * @return double
 */
function get_microtime()
{
    return microtime(true);
}

/**
 * @return string
 */
function page_creation_timer()
{
    if (!isset($GLOBALS['pc_start_time'])) {
        $GLOBALS['pc_start_time'] = get_microtime();
    } else {
        $pc_end_time = get_microtime();
        $pc_total_time = $pc_end_time - $GLOBALS['pc_start_time'];

        return sprintf(__('pggen03') . ' %f ' . __('seconds03') . "\n", $pc_total_time);
    }
}

/**
 * @param $text
 */
function debug($text)
{
    if (true === DEBUG && headers_sent()) {
        echo "<!-- DEBUG: $text -->\n";
    }
}

/**
 * @param $dir
 * @return bool|int
 *
 * @todo rewrite using SPL
 */
function count_files_in_dir($dir)
{
    $file_list_array = @scandir($dir);
    if ($file_list_array === false) {
        return false;
    }

    //there is always . and .. so reduce the count
    return count($file_list_array) - 2;
}

/**
 * @param string $message_headers
 * @return array|bool
 */
function get_mail_relays($message_headers)
{
    $headers = explode("\\n", $message_headers);
    $relays = null;
    foreach ($headers as $header) {
        $header = preg_replace('/IPv6\:/', '', $header);
        if (preg_match_all('/Received.+\[(?P<ip>[\dabcdef.:]+)\]/', $header, $regs)) {
            foreach ($regs['ip'] as $relay) {
                if (false !== filter_var($relay, FILTER_VALIDATE_IP)) {
                    $relays[] = $relay;
                }
            }
        }
    }
    if (is_array($relays)) {
        return array_unique($relays);
    }

    return false;
}

/**
 * @param array $addresses
 * @param string $type
 * @return string
 */
function address_filter_sql($addresses, $type)
{
    $sqladdr = '';
    $sqladdr_arr = [];
    switch ($type) {
        case 'A': // Administrator - show everything
            $sqladdr = '1=1';
            break;
        case 'U': // User - show only specific addresses
            foreach ($addresses as $address) {
                if (defined('FILTER_TO_ONLY') && FILTER_TO_ONLY) {
                    $sqladdr_arr[] = "to_address like '$address%'";
                } else {
                    $sqladdr_arr[] = "to_address like '$address%' OR from_address = '$address'";
                }
            }
            $sqladdr = implode(' OR ', $sqladdr_arr);
            break;
        case 'D': // Domain administrator
            foreach ($addresses as $address) {
                if (strpos($address, '@')) {
                    if (defined('FILTER_TO_ONLY') && FILTER_TO_ONLY) {
                        $sqladdr_arr[] = "to_address like '%$address%'";
                    } else {
                        $sqladdr_arr[] = "to_address like '%$address%' OR from_address = '$address'";
                    }
                } else {
                    if (defined('FILTER_TO_ONLY') && FILTER_TO_ONLY) {
                        $sqladdr_arr[] = "to_domain='$address'";
                    } else {
                        $sqladdr_arr[] = "to_domain='$address' OR from_domain='$address'";
                    }
                }
            }
            // Join together to form a suitable SQL WHERE clause
            $sqladdr = implode(' OR ', $sqladdr_arr);
            break;
        case 'H': // Host
            foreach ($addresses as $hostname) {
                $sqladdr_arr[] = "hostname='$hostname'";
            }
            $sqladdr = implode(' OR ', $sqladdr_arr);
            break;
    }

    return $sqladdr;
}

/**
 * @param string $username
 * @param string $password
 * @return null|string
 */
function ldap_authenticate($username, $password)
{
    $username = ldap_escape(strtolower($username), '', LDAP_ESCAPE_DN);
    if ($username !== '' && $password !== '') {
        $ds = ldap_connect(LDAP_HOST, LDAP_PORT) or die(__('ldpaauth103') . ' ' . LDAP_HOST);

        $ldap_protocol_version = 3;
        if (defined('LDAP_PROTOCOL_VERSION')) {
            $ldap_protocol_version = LDAP_PROTOCOL_VERSION;
        }
        // Check if Microsoft Active Directory compatibility is enabled
        if (defined('LDAP_MS_AD_COMPATIBILITY') && LDAP_MS_AD_COMPATIBILITY === true) {
            ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);
            $ldap_protocol_version = 3;
        }
        ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, $ldap_protocol_version);

        $bindResult = @ldap_bind($ds, LDAP_USER, LDAP_PASS);
        if (false === $bindResult) {
            die(ldap_print_error($ds));
        }

        //search for $user in LDAP directory
        $ldap_search_results = ldap_search($ds, LDAP_DN, sprintf(LDAP_FILTER, $username)) or die(__('ldpaauth203'));

        if (false === $ldap_search_results) {
            @trigger_error(__('ldapnoresult03') . ' "' . $username . '"');

            return null;
        }
        if (1 > ldap_count_entries($ds, $ldap_search_results)) {
            //
            @trigger_error(__('ldapresultnodata03') . ' "' . $username . '"');

            return null;
        }
        if (ldap_count_entries($ds, $ldap_search_results) > 1) {
            @trigger_error(__('ldapresultset03') . ' "' . $username . '" ' . __('ldapisunique03'));

            return null;
        }

        if ($ldap_search_results) {
            $result = ldap_get_entries($ds, $ldap_search_results) or die(__('ldpaauth303'));
            ldap_free_result($ldap_search_results);
            if (isset($result[0])) {
                if (in_array('group', array_values($result[0]['objectclass']), true)) {
                    // do not login as group
                    return null;
                }

                if (!isset($result[0][LDAP_USERNAME_FIELD], $result[0][LDAP_USERNAME_FIELD][0])) {
                    @trigger_error(__('ldapno03') . ' "' . LDAP_USERNAME_FIELD . '" ' . __('ldapresults03'));

                    return null;
                }

                $user = $result[0][LDAP_USERNAME_FIELD][0];
                if (defined('LDAP_BIND_PREFIX')) {
                    $user = LDAP_BIND_PREFIX . $user;
                }
                if (defined('LDAP_BIND_SUFFIX')) {
                    $user .= LDAP_BIND_SUFFIX;
                }

                if (!isset($result[0][LDAP_EMAIL_FIELD])) {
                    @trigger_error(__('ldapno03') . ' "' . LDAP_EMAIL_FIELD . '" ' . __('ldapresults03'));

                    return null;
                }

                $bindResult = @ldap_bind($ds, $user, $password);
                if (false !== $bindResult) {
                    foreach ($result[0][LDAP_EMAIL_FIELD] as $email) {
                        if (0 === strpos($email, 'SMTP')) {
                            $email = strtolower(substr($email, 5));
                            break;
                        }
                    }

                    if (!isset($email)) {
                        //user has no mail but it is required for mailwatch
                        return null;
                    }

                    $sql = sprintf('SELECT username FROM users WHERE username = %s', \MailWatch\Sanitize::quote_smart($email));
                    $sth = dbquery($sql);
                    if ($sth->num_rows === 0) {
                        $sql = sprintf(
                            "REPLACE INTO users (username, fullname, type, password) VALUES (%s, %s,'U',NULL)",
                             \MailWatch\Sanitize::quote_smart($email),
                             \MailWatch\Sanitize::quote_smart($result[0]['cn'][0])
                        );
                        dbquery($sql);
                    }

                    return $email;
                }

                if (ldap_errno($ds) === 49) {
                    //LDAP_INVALID_CREDENTIALS
                    return null;
                }
                die(ldap_print_error($ds));
            }
        }
    }

    return null;
}

/**
 * @param Resource $ds
 * @return string
 */
function ldap_print_error($ds)
{
    return sprintf(
        __('ldapnobind03'),
        LDAP_HOST,
        ldap_errno($ds),
        ldap_error($ds)
    );
}

if (!function_exists('ldap_escape')) {
    define('LDAP_ESCAPE_FILTER', 0x01);
    define('LDAP_ESCAPE_DN', 0x02);

    /**
     * function ldap_escape
     *
     * @source http://stackoverflow.com/questions/8560874/php-ldap-add-function-to-escape-ldap-special-characters-in-dn-syntax#answer-8561604
     * @author Chris Wright
     * @param string $subject The subject string
     * @param string $ignore Set of characters to leave untouched
     * @param int $flags Any combination of LDAP_ESCAPE_* flags to indicate the
     *                   set(s) of characters to escape.
     * @return string The escaped string
     */
    function ldap_escape($subject, $ignore = '', $flags = 0)
    {
        $charMaps = [
            LDAP_ESCAPE_FILTER => ['\\', '*', '(', ')', "\x00"],
            LDAP_ESCAPE_DN => ['\\', ',', '=', '+', '<', '>', ';', '"', '#']
        ];

        // Pre-process the char maps on first call
        if (!isset($charMaps[0])) {
            $charMaps[0] = [];
            for ($i = 0; $i < 256; $i++) {
                $charMaps[0][chr($i)] = sprintf('\\%02x', $i);
            }

            for ($i = 0, $l = count($charMaps[LDAP_ESCAPE_FILTER]); $i < $l; $i++) {
                $chr = $charMaps[LDAP_ESCAPE_FILTER][$i];
                unset($charMaps[LDAP_ESCAPE_FILTER][$i]);
                $charMaps[LDAP_ESCAPE_FILTER][$chr] = $charMaps[0][$chr];
            }

            for ($i = 0, $l = count($charMaps[LDAP_ESCAPE_DN]); $i < $l; $i++) {
                $chr = $charMaps[LDAP_ESCAPE_DN][$i];
                unset($charMaps[LDAP_ESCAPE_DN][$i]);
                $charMaps[LDAP_ESCAPE_DN][$chr] = $charMaps[0][$chr];
            }
        }

        // Create the base char map to escape
        $flags = (int)$flags;
        $charMap = [];
        if ($flags & LDAP_ESCAPE_FILTER) {
            $charMap += $charMaps[LDAP_ESCAPE_FILTER];
        }
        if ($flags & LDAP_ESCAPE_DN) {
            $charMap += $charMaps[LDAP_ESCAPE_DN];
        }
        if (!$charMap) {
            $charMap = $charMaps[0];
        }

        // Remove any chars to ignore from the list
        $ignore = (string)$ignore;
        for ($i = 0, $l = strlen($ignore); $i < $l; $i++) {
            unset($charMap[$ignore[$i]]);
        }

        // Do the main replacement
        $result = strtr($subject, $charMap);

        // Encode leading/trailing spaces if LDAP_ESCAPE_DN is passed
        if ($flags & LDAP_ESCAPE_DN) {
            if ($result[0] === ' ') {
                $result = '\\20' . substr($result, 1);
            }
            if ($result[strlen($result) - 1] === ' ') {
                $result = substr($result, 0, -1) . '\\20';
            }
        }

        return $result;
    }
}

/**
 * @param $entry
 * @return string
 */
function ldap_get_conf_var($entry)
{
    // Translate MailScanner.conf vars to internal
    $entry = translate_etoi($entry);

    $lh = ldap_connect(LDAP_HOST, LDAP_PORT)
    or die(__('ldapgetconfvar103') . ' ' . LDAP_HOST . "\n");

    @ldap_bind($lh)
    or die(__('ldapgetconfvar203') . "\n");

    # As per MailScanner Config.pm
    $filter = '(objectClass=mailscannerconfmain)';
    $filter = "(&$filter(mailScannerConfBranch=main))";

    $sh = ldap_search($lh, LDAP_DN, $filter, [$entry]);

    $info = ldap_get_entries($lh, $sh);
    if ($info['count'] > 0 && $info[0]['count'] !== 0) {
        if ($info[0]['count'] === 0) {
            // Return single value
            return $info[0][$info[0][0]][0];
        }

        // Multi-value option, build array and return as space delimited
        $return = [];
        for ($n = 0; $n < $info[0][$info[0][0]]['count']; $n++) {
            $return[] = $info[0][$info[0][0]][$n];
        }

        return implode(' ', $return);
    }

    // No results
    die(__('ldapgetconfvar303') . " '$entry' " . __('ldapgetconfvar403') . "\n");
}

/**
 * @param $entry
 * @return bool
 */
function ldap_get_conf_truefalse($entry)
{
    // Translate MailScanner.conf vars to internal
    $entry = translate_etoi($entry);

    $lh = ldap_connect(LDAP_HOST, LDAP_PORT)
    or die(__('ldapgetconfvar103') . ' ' . LDAP_HOST . "\n");

    @ldap_bind($lh)
    or die(__('ldapgetconfvar203') . "\n");

    # As per MailScanner Config.pm
    $filter = '(objectClass=mailscannerconfmain)';
    $filter = "(&$filter(mailScannerConfBranch=main))";

    $sh = ldap_search($lh, LDAP_DN, $filter, [$entry]);

    $info = ldap_get_entries($lh, $sh);
    debug(debug_print_r($info));
    if ($info['count'] > 0) {
        debug('Entry: ' . debug_print_r($info[0][$info[0][0]][0]));
        switch ($info[0][$info[0][0]][0]) {
            case 'yes':
            case '1':
                return true;
            case 'no':
            case '0':
            default:
                return false;
        }
    } else {
        // No results
        //die(__('ldapgetconfvar303') . " '$entry' " . __('ldapgetconfvar403') . "\n");
        return false;
    }
}

/**
 * @param string $username
 * @param string $password
 * @return null|string
 */
function imap_authenticate($username, $password)
{
    $username = strtolower($username);

    if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
        //user has no mail but it is required for mailwatch
        return null;
    }

    if ($username !== '' && $password !== '') {
        $mbox = imap_open(IMAP_HOST, $username, $password, null, 0);

        if (false === $mbox) {
            //auth faild
            return null;
        }

        if (defined('IMAP_AUTOCREATE_VALID_USER') && IMAP_AUTOCREATE_VALID_USER === true) {
            $sql = sprintf('SELECT username FROM users WHERE username = %s', \MailWatch\Sanitize::quote_smart($username));
            $sth = dbquery($sql);
            if ($sth->num_rows === 0) {
                $sql = sprintf(
                    "REPLACE INTO users (username, fullname, type, password) VALUES (%s, %s,'U',NULL)",
                     \MailWatch\Sanitize::quote_smart($username),
                     \MailWatch\Sanitize::quote_smart($password)
                );
                dbquery($sql);
            }
        }

        return $username;
    }

    return null;
}

/**
 * @param $name
 * @return string
 */
function translate_etoi($name)
{
    $name = strtolower($name);
    $file = MS_SHARE_DIR . 'perl/MailScanner/ConfigDefs.pl';
    $fh = fopen($file, 'rb')
    or die(__('dietranslateetoi03') . " $file\n");
    $etoi = [];
    while (!feof($fh)) {
        $line = rtrim(fgets($fh, filesize($file)));
        if (preg_match('/^([^#].+)\s=\s([^#].+)/i', $line, $regs)) {
            // Lowercase all values
            $regs[1] = strtolower($regs[1]);
            $regs[2] = strtolower($regs[2]);
            $etoi[rtrim($regs[2])] = rtrim($regs[1]);
        }
    }
    fclose($fh) or die($php_errormsg);
    if (isset($etoi["$name"])) {
        return $etoi["$name"];
    }

    return $name;
}

/**
 * @param $input
 * @return string
 */
function decode_header($input)
{
    // Remove white space between encoded-words
    $input = preg_replace('/(=\?[^?]+\?(q|b)\?[^?]*\?=)(\s)+=\?/i', '\1=?', $input);
    // For each encoded-word...
    while (preg_match('/(=\?([^?]+)\?(q|b)\?([^?]*)\?=)/i', $input, $matches)) {
        $encoded = $matches[1];
        //$charset = $matches[2];
        $encoding = $matches[3];
        $text = $matches[4];
        switch (strtolower($encoding)) {
            case 'b':
                $text = base64_decode($text);
                break;
            case 'q':
                $text = str_replace('_', ' ', $text);
                preg_match_all('/=([a-f0-9]{2})/i', $text, $matches);
                foreach ($matches[1] as $value) {
                    $text = str_replace('=' . $value, chr(hexdec($value)), $text);
                }
                break;
        }
        $input = str_replace($encoded, $text, $input);
    }

    return $input;
}

/**
 * @param $input
 * @return string
 */
function debug_print_r($input)
{
    ob_start();
    print_r($input);
    $return = ob_get_contents();
    ob_end_clean();

    return $return;
}

/**
 * @param $ip
 * @return bool
 */
function return_geoip_country($ip)
{
    //check if ipv4 has a port specified (e.g. 10.0.0.10:1025), strip it if found
    $ip = stripPortFromIp($ip);
    $countryname = false;
    if (strpos($ip, ':') === false) {
        //ipv4
        if (file_exists(__DIR__ . '/temp/GeoIP.dat') && filesize(__DIR__ . '/temp/GeoIP.dat') > 0) {
            $gi = geoip_open(__DIR__ . '/temp/GeoIP.dat', GEOIP_STANDARD);
            $countryname = geoip_country_name_by_addr($gi, $ip);
            geoip_close($gi);
        }
    } else {
        //ipv6
        if (file_exists(__DIR__ . '/temp/GeoIPv6.dat') && filesize(__DIR__ . '/temp/GeoIPv6.dat') > 0) {
            $gi = geoip_open(__DIR__ . '/temp/GeoIPv6.dat', GEOIP_STANDARD);
            $countryname = geoip_country_name_by_addr_v6($gi, $ip);
            geoip_close($gi);
        }
    }

    return $countryname;
}

/**
 * @param $ip
 * @return string
 */
function stripPortFromIp($ip)
{
    if (preg_match('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\:\d{1,5}/', $ip)) {
        $ip = current(array_slice(explode(':', $ip), 0, 1));
    }

    return $ip;
}

/**
 * @param string $input
 * @return array
 */
function quarantine_list($input = '/')
{
    $quarantinedir = \MailWatch\MailScanner::getConfVar('QuarantineDir') . '/';
    $item = [];
    if ($input === '/') {

        // Return top-level directory
        $d = @opendir($quarantinedir);

        while (false !== ($f = @readdir($d))) {
            if ($f !== '.' && $f !== '..') {
                $item[] = $f;
            }
        }
        @closedir($d);
    } else {
        $current_dir = $quarantinedir . $input;
        $dirs = [$current_dir, $current_dir . '/spam', $current_dir . '/nonspam', $current_dir . '/mcp'];
        foreach ($dirs as $dir) {
            if (is_dir($dir) && is_readable($dir)) {
                $d = @opendir($dir);
                while (false !== ($f = readdir($d))) {
                    if ($f !== '.' && $f !== '..') {
                        $item[] = "'$f'";
                    }
                }
                closedir($d);
            }
        }
    }

    if (count($item) > 0) {
        // Sort in reverse chronological order
        arsort($item);
    }

    return $item;
}

/**
 * @param $host
 * @return bool
 */
function is_local($host)
{
    $host = strtolower($host);
    // Is RPC required to look-up??
    $sys_hostname = strtolower(rtrim(gethostname()));
    switch ($host) {
        case $sys_hostname:
        case gethostbyaddr('127.0.0.1'):
            return true;
        default:
            // Remote - RPC needed
            return false;
    }
}

/**
 * @param string $msgid
 * @param bool|false $rpc_only
 * @return array|mixed|string
 */
function quarantine_list_items($msgid, $rpc_only = false)
{
    $sql = "
SELECT
  hostname,
  DATE_FORMAT(date,'%Y%m%d') AS date,
  id,
  to_address,
  CASE WHEN isspam>0 THEN 'Y' ELSE 'N' END AS isspam,
  CASE WHEN nameinfected>0 THEN 'Y' ELSE 'N' END AS nameinfected,
  CASE WHEN virusinfected>0 THEN 'Y' ELSE 'N' END AS virusinfected,
  CASE WHEN otherinfected>0 THEN 'Y' ELSE 'N' END AS otherinfected
 FROM
  maillog
 WHERE
  id = '$msgid'";
    $sth = dbquery($sql);
    $rows = $sth->num_rows;
    if ($rows <= 0) {
        die(__('diequarantine103') . " $msgid " . __('diequarantine103') . "\n");
    }
    $row = $sth->fetch_object();
    if (!$rpc_only && is_local($row->hostname)) {
        $quarantinedir = \MailWatch\MailScanner::getConfVar('QuarantineDir');
        $quarantine = $quarantinedir . '/' . $row->date . '/' . $row->id;
        $spam = $quarantinedir . '/' . $row->date . '/spam/' . $row->id;
        $nonspam = $quarantinedir . '/' . $row->date . '/nonspam/' . $row->id;
        $mcp = $quarantinedir . '/' . $row->date . '/mcp/' . $row->id;
        $infected = 'N';
        if ($row->virusinfected === 'Y' || $row->nameinfected === 'Y' || $row->otherinfected === 'Y') {
            $infected = 'Y';
        }
        $quarantined = [];
        $count = 0;
        foreach ([$nonspam, $spam, $mcp] as $category) {
            if (file_exists($category) && is_readable($category)) {
                $quarantined[$count]['id'] = $count;
                $quarantined[$count]['host'] = $row->hostname;
                $quarantined[$count]['msgid'] = $row->id;
                $quarantined[$count]['to'] = $row->to_address;
                $quarantined[$count]['file'] = 'message';
                $quarantined[$count]['type'] = 'message/rfc822';
                $quarantined[$count]['path'] = $category;
                $quarantined[$count]['md5'] = md5($category);
                $quarantined[$count]['dangerous'] = $infected;
                $quarantined[$count]['isspam'] = $row->isspam;
                $count++;
            }
        }
        // Check the main quarantine
        if (is_dir($quarantine) && is_readable($quarantine)) {
            $d = opendir($quarantine) or die(__('diequarantine303') . " $quarantine\n");
            while (false !== ($f = readdir($d))) {
                if ($f !== '..' && $f !== '.') {
                    $quarantined[$count]['id'] = $count;
                    $quarantined[$count]['host'] = $row->hostname;
                    $quarantined[$count]['msgid'] = $row->id;
                    $quarantined[$count]['to'] = $row->to_address;
                    $quarantined[$count]['file'] = $f;
                    $file = escapeshellarg($quarantine . '/' . $f);
                    $quarantined[$count]['type'] = ltrim(rtrim(`/usr/bin/file -bi $file`));
                    $quarantined[$count]['path'] = $quarantine . '/' . $f;
                    $quarantined[$count]['md5'] = md5($quarantine . '/' . $f);
                    $quarantined[$count]['dangerous'] = $infected;
                    $quarantined[$count]['isspam'] = $row->isspam;
                    $count++;
                }
            }
            closedir($d);
        }

        return $quarantined;
    }

    // Host is remote call quarantine_list_items by RPC
    debug("Calling quarantine_list_items on $row->hostname by XML-RPC");
    //$client = new xmlrpc_client(constant('RPC_RELATIVE_PATH').'/rpcserver.php',$row->hostname,80);
    //if(DEBUG) { $client->setDebug(1); }
    //$parameters = array($input);
    //$msg = new xmlrpcmsg('quarantine_list_items',$parameters);
    $msg = new xmlrpcmsg('quarantine_list_items', [new xmlrpcval($msgid)]);
    $rsp = xmlrpc_wrapper($row->hostname, $msg); //$client->send($msg);
    if ($rsp->faultCode() === 0) {
        $response = php_xmlrpc_decode($rsp->value());
    } else {
        $response = 'XML-RPC Error: ' . $rsp->faultString();
    }

    return $response;
}

/**
 * @param array $list
 * @param array $num
 * @param string $to
 * @param bool|false $rpc_only
 * @return string
 */
function quarantine_release($list, $num, $to, $rpc_only = false)
{
    if (!is_array($list) || !isset($list[0]['msgid'])) {
        return 'Invalid argument';
    }

    $new = quarantine_list_items($list[0]['msgid']);
    $list =& $new;

    if (!$rpc_only && is_local($list[0]['host'])) {
        if (!QUARANTINE_USE_SENDMAIL) {
            $hdrs = ['From' => MAILWATCH_FROM_ADDR, 'Subject' => \ForceUTF8\Encoding::toUTF8(QUARANTINE_SUBJECT), 'Date' => date('r')];
            $mailMimeParams = [
                'eol' => "\r\n",
                'html_charset' => 'UTF-8',
                'text_charset' => 'UTF-8',
                'head_charset' => 'UTF-8'
            ];
            $mime = new Mail_mime($mailMimeParams);
            $mime->setTXTBody(\ForceUTF8\Encoding::toUTF8(QUARANTINE_MSG_BODY));
            // Loop through each selected file and attach them to the mail
            foreach ($num as $key => $val) {
                // If the message is of rfc822 type then set it as Quoted printable
                if (preg_match('/message\/rfc822/', $list[$val]['type'])) {
                    $mime->addAttachment($list[$val]['path'], 'message/rfc822', 'Original Message', true, '');
                } else {
                    // Default is base64 encoded
                    $mime->addAttachment($list[$val]['path'], $list[$val]['type'], $list[$val]['file'], true);
                }
            }
            $mail_param = ['host' => MAILWATCH_MAIL_HOST];
            $body = $mime->get();
            $hdrs = $mime->headers($hdrs);
            $mail = new Mail_smtp($mail_param);

            $m_result = $mail->send($to, $hdrs, $body);
            if (is_a($m_result, 'PEAR_Error')) {
                // Error
                $status = __('releaseerror03') . ' (' . $m_result->getMessage() . ')';
                global $error;
                $error = true;
            } else {
                $sql = "UPDATE maillog SET released = '1' WHERE id = '" .  \MailWatch\Sanitize::safe_value($list[0]['msgid']) . "'";
                dbquery($sql);
                $status = __('releasemessage03') . ' ' . str_replace(',', ', ', $to);
                audit_log(sprintf(__('auditlogquareleased03', true), $list[0]['msgid']) . ' ' . $to);
            }

            return $status;
        }

        // Use sendmail to release message
        // We can only release message/rfc822 files in this way.
        $cmd = QUARANTINE_SENDMAIL_PATH . ' -i -f ' . MAILWATCH_FROM_ADDR . ' ' . escapeshellarg($to) . ' < ';
        foreach ($num as $key => $val) {
            if (preg_match('/message\/rfc822/', $list[$val]['type'])) {
                debug($cmd . $list[$val]['path']);
                exec($cmd . $list[$val]['path'] . ' 2>&1', $output_array, $retval);
                if ($retval === 0) {
                    $sql = "UPDATE maillog SET released = '1' WHERE id = '" .  \MailWatch\Sanitize::safe_value($list[0]['msgid']) . "'";
                    dbquery($sql);
                    $status = __('releasemessage03') . ' ' . str_replace(',', ', ', $to);
                    audit_log(sprintf(__('auditlogquareleased03', true), $list[$val]['msgid']) . ' ' . $to);
                } else {
                    $status = __('releaseerrorcode03') . ' ' . $retval . ' ' . __('returnedfrom03') . "\n" . implode(
                            "\n",
                            $output_array
                        );
                    global $error;
                    $error = true;
                }

                return $status;
            }
        }
    } else {
        // Host is remote - handle by RPC
        debug('Calling quarantine_release on ' . $list[0]['host'] . ' by XML-RPC');
        //$client = new xmlrpc_client(constant('RPC_RELATIVE_PATH').'/rpcserver.php',$list[0]['host'],80);
        // Convert input parameters
        $list_output = [];
        foreach ($list as $list_array) {
            $list_struct = [];
            foreach ($list_array as $key => $val) {
                $list_struct[$key] = new xmlrpcval($val);
            }
            $list_output[] = new xmlrpcval($list_struct, 'struct');
        }
        $num_output = [];
        foreach ($num as $key => $val) {
            $num_output[$key] = new xmlrpcval($val);
        }
        // Build input parameters
        $param1 = new xmlrpcval($list_output, 'array');
        $param2 = new xmlrpcval($num_output, 'array');
        $param3 = new xmlrpcval($to, 'string');
        $parameters = [$param1, $param2, $param3];
        $msg = new xmlrpcmsg('quarantine_release', $parameters);
        $rsp = xmlrpc_wrapper($list[0]['host'], $msg); //$client->send($msg);
        if ($rsp->faultCode() === 0) {
            $response = php_xmlrpc_decode($rsp->value());
        } else {
            $response = 'XML-RPC Error: ' . $rsp->faultString();
        }

        return $response . ' (RPC)';
    }
}

/**
 * @param $list
 * @param $num
 * @param $type
 * @param bool|false $rpc_only
 * @return string
 */
function quarantine_learn($list, $num, $type, $rpc_only = false)
{
    \MailWatch\Db::connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (!is_array($list) || !isset($list[0]['msgid'])) {
        return 'Invalid argument';
    }
    $new = quarantine_list_items($list[0]['msgid']);
    $list =& $new;
    $status = [];
    if (!$rpc_only && is_local($list[0]['host'])) {
        //prevent sa-learn process blocking complete apache server
        session_write_close();
        foreach ($num as $key => $val) {
            $use_spamassassin = false;
            $isfn = '0';
            $isfp = '0';
            switch ($type) {
                case 'ham':
                    $learn_type = 'ham';
                    // Learning SPAM as HAM - this is a false-positive
                    $isfp = ($list[$val]['isspam'] === 'Y' ? '1' : '0');
                    break;
                case 'spam':
                    $learn_type = 'spam';
                    // Learning HAM as SPAM - this is a false-negative
                    $isfn = ($list[$val]['isspam'] === 'N' ? '1' : '0');
                    break;
                case 'forget':
                    $learn_type = 'forget';
                    break;
                case 'report':
                    $use_spamassassin = true;
                    $learn_type = '-r';
                    $isfn = '1';
                    break;
                case 'revoke':
                    $use_spamassassin = true;
                    $learn_type = '-k';
                    $isfp = '1';
                    break;
                default:
                    //TODO handle this case
                    $isfp = null;
            }
            if ($isfp !== null) {
                $sql = 'UPDATE maillog SET isfp=' . $isfp . ', isfn=' . $isfn . " WHERE id='"
                    .  \MailWatch\Sanitize::safe_value($list[$val]['msgid']) . "'";
            }

            if (true === $use_spamassassin) {
                // Run SpamAssassin to report or revoke spam/ham
                exec(
                    SA_DIR . 'spamassassin -p ' . SA_PREFS . ' ' . $learn_type . ' < ' . $list[$val]['path'] . ' 2>&1',
                    $output_array,
                    $retval
                );
                if ($retval === 0) {
                    // Command succeeded - update the database accordingly
                    if (isset($sql)) {
                        debug("Learner - running SQL: $sql");
                        dbquery($sql);
                    }
                    $status[] = __('spamassassin03') . ' ' . implode(', ', $output_array);
                    switch ($learn_type) {
                        case '-r':
                            $learn_type = 'spam';
                            break;
                        case '-k':
                            $learn_type = 'ham';
                            break;
                    }
                    audit_log(
                        sprintf(__('auditlogquareleased03', true) . ' ', $list[$val]['msgid']) . ' ' . $learn_type
                    );
                } else {
                    $status[] = __('spamerrorcode0103') . ' ' . $retval . __('spamerrorcode0203') . "\n" . implode(
                            "\n",
                            $output_array
                        );
                    global $error;
                    $error = true;
                }
            } else {
                // Only sa-learn required
                $max_size_option = '';
                if (defined('SA_MAXSIZE') && is_int(SA_MAXSIZE) && SA_MAXSIZE > 0) {
                    $max_size_option = ' --max-size ' . SA_MAXSIZE;
                }

                exec(
                    SA_DIR . 'sa-learn -p ' . SA_PREFS . ' --' . $learn_type . ' --file ' . $list[$val]['path'] . $max_size_option . ' 2>&1',
                    $output_array,
                    $retval
                );

                if ($retval === 0) {
                    // Command succeeded - update the database accordingly
                    if (isset($sql)) {
                        debug("Learner - running SQL: $sql");
                        dbquery($sql);
                    }
                    $status[] = __('salearn03') . ' ' . implode(', ', $output_array);
                    audit_log(sprintf(__('auditlogspamtrained03', true), $list[$val]['msgid']) . ' ' . $learn_type);
                } else {
                    $status[] = __('salearnerror03') . ' ' . $retval . ' ' . __('salearnreturn03') . "\n" . implode(
                            "\n",
                            $output_array
                        );
                    global $error;
                    $error = true;
                }
            }
            if (!isset($error)) {
                if ($learn_type === 'spam') {
                    $numeric_type = 2;
                }
                if ($learn_type === 'ham') {
                    $numeric_type = 1;
                }
                if (isset($numeric_type)) {
                    $sql = "UPDATE `maillog` SET salearn = '$numeric_type' WHERE id = '" .  \MailWatch\Sanitize::safe_value($list[$val]['msgid']) . "'";
                    dbquery($sql);
                }
            }
        }

        return implode("\n", $status);
    }

    // Call by RPC
    debug('Calling quarantine_learn on ' . $list[0]['host'] . ' by XML-RPC');
    //$client = new xmlrpc_client(constant('RPC_RELATIVE_PATH').'/rpcserver.php',$list[0]['host'],80);
    // Convert input parameters
    $list_output = [];
    foreach ($list as $list_array) {
        $list_struct = [];
        foreach ($list_array as $key => $val) {
            $list_struct[$key] = new xmlrpcval($val);
        }
        $list_output[] = new xmlrpcval($list_struct, 'struct');
    }
    $num_output = [];
    foreach ($num as $key => $val) {
        $num_output[$key] = new xmlrpcval($val);
    }
    // Build input parameters
    $param1 = new xmlrpcval($list_output, 'array');
    $param2 = new xmlrpcval($num_output, 'array');
    $param3 = new xmlrpcval($type, 'string');
    $parameters = [$param1, $param2, $param3];
    $msg = new xmlrpcmsg('quarantine_learn', $parameters);
    $rsp = xmlrpc_wrapper($list[0]['host'], $msg); //$client->send($msg);
    if ($rsp->faultCode() === 0) {
        $response = php_xmlrpc_decode($rsp->value());
    } else {
        $response = 'XML-RPC Error: ' . $rsp->faultString();
    }

    return $response . ' (RPC)';
}

/**
 * @param $list
 * @param $num
 * @param bool|false $rpc_only
 * @return string
 */
function quarantine_delete($list, $num, $rpc_only = false)
{
    if (!is_array($list) || !isset($list[0]['msgid'])) {
        return 'Invalid argument';
    }

    $new = quarantine_list_items($list[0]['msgid']);
    $list =& $new;

    if (!$rpc_only && is_local($list[0]['host'])) {
        $status = [];
        foreach ($num as $key => $val) {
            if (@unlink($list[$val]['path'])) {
                $status[] = 'Delete: deleted file ' . $list[$val]['path'];
                dbquery("UPDATE maillog SET quarantined=NULL WHERE id='" . $list[$val]['msgid'] . "'");
                audit_log(__('auditlogdelqua03', true) . ' ' . $list[$val]['path']);
            } else {
                $status[] = __('auditlogdelerror03') . ' ' . $list[$val]['path'];
                global $error;
                $error = true;
            }
        }

        return implode("\n", $status);
    }

    // Call by RPC
    debug('Calling quarantine_delete on ' . $list[0]['host'] . ' by XML-RPC');
    //$client = new xmlrpc_client(constant('RPC_RELATIVE_PATH').'/rpcserver.php',$list[0]['host'],80);
    // Convert input parameters
    $list_output = [];
    foreach ($list as $list_array) {
        $list_struct = [];
        foreach ($list_array as $key => $val) {
            $list_struct[$key] = new xmlrpcval($val);
        }
        $list_output[] = new xmlrpcval($list_struct, 'struct');
    }
    $num_output = [];
    foreach ($num as $key => $val) {
        $num_output[$key] = new xmlrpcval($val);
    }
    // Build input parameters
    $param1 = new xmlrpcval($list_output, 'array');
    $param2 = new xmlrpcval($num_output, 'array');
    $parameters = [$param1, $param2];
    $msg = new xmlrpcmsg('quarantine_delete', $parameters);
    $rsp = xmlrpc_wrapper($list[0]['host'], $msg); //$client->send($msg);
    if ($rsp->faultCode() === 0) {
        $response = php_xmlrpc_decode($rsp->value());
    } else {
        $response = 'XML-RPC Error: ' . $rsp->faultString();
    }

    return $response . ' (RPC)';
}

/**
 * @param $id
 * @return mixed
 */
function fixMessageId($id)
{
    $mta = \MailWatch\MailScanner::getConfVar('mta');
    if ($mta === 'postfix') {
        $id = str_replace('_', '.', $id);
    }

    return $id;
}

/**
 * @param string $action
 * @return bool
 */
function audit_log($action)
{
    $link = \MailWatch\Db::connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (AUDIT) {
        $user = 'unknown';
        if (isset($_SESSION['myusername'])) {
            $user = $link->real_escape_string($_SESSION['myusername']);
        }

        $action =  \MailWatch\Sanitize::safe_value($action);
        $ip =  \MailWatch\Sanitize::safe_value($_SERVER['REMOTE_ADDR']);
        $ret = dbquery("INSERT INTO audit_log (user, ip_address, action) VALUES ('$user', '$ip', '$action')");
        if ($ret) {
            return true;
        }
    }

    return false;
}

/**
 * @param $array
 * @return array|number
 */
function mailwatch_array_sum($array)
{
    if (!is_array($array)) {
        // Not an array
        return [];
    }

    return array_sum($array);
}

/**
 * @param $file
 * @return mixed
 */
function read_ruleset_default($file)
{
    $fh = fopen($file, 'rb') or die(__('diereadruleset03') . " ($file)");
    while (!feof($fh)) {
        $line = rtrim(fgets($fh, filesize($file)));
        if (preg_match('/(\S+)\s+(\S+)\s+(\S+)/', $line, $regs)) {
            if (strtolower($regs[2]) === 'default') {
                // Check that it isn't another ruleset
                if (is_file($regs[3])) {
                    return read_ruleset_default($regs[3]);
                }

                return $regs[3];
            }
        }
    }

    return '';
}



/**
 * @return array
 */
function return_quarantine_dates()
{
    $array = [];
    for ($d = 0; $d < QUARANTINE_DAYS_TO_KEEP; $d++) {
        $array[] = date('Ymd', mktime(0, 0, 0, date('m'), date('d') - $d, date('Y')));
    }

    return $array;
}



/**
 * @return bool
 */
function is_rpc_client_allowed()
{
    // If no server address supplied
    if (!isset($_SERVER['SERVER_ADDR']) || empty($_SERVER['SERVER_ADDR'])) {
        return true;
    }
    // Get list of allowed clients
    if (defined('RPC_ALLOWED_CLIENTS') && (!RPC_ALLOWED_CLIENTS === false)) {
        // Read in space separated list
        $clients = explode(' ', constant('RPC_ALLOWED_CLIENTS'));
        // Validate each client type
        foreach ($clients as $client) {
            if ($client === 'allprivate' && ip_in_range($_SERVER['SERVER_ADDR'], false, 'private')) {
                return true;
            }
            if ($client === 'local24') {
                // Get machine IP address from the hostname
                $ip = gethostbyname(rtrim(gethostname()));
                // Change IP address to a /24 network
                $ipsplit = explode('.', $ip);
                $ipsplit[3] = '0';
                $ip = implode('.', $ipsplit);
                if (ip_in_range($_SERVER['SERVER_ADDR'], "{$ip}/24")) {
                    return true;
                }
            }
            // All any others
            if (ip_in_range($_SERVER['SERVER_ADDR'], $client)) {
                return true;
            }
            // Try hostname
            $iplookup = gethostbyname($client);
            if ($client !== $iplookup && ip_in_range($_SERVER['SERVER_ADDR'], $iplookup)) {
                return true;
            }
        }

        // If all else fails
        return false;
    }

    return false;
}

/**
 * @param $host
 * @param $msg
 * @return xmlrpcresp
 */
function xmlrpc_wrapper($host, $msg)
{
    $method = 'http';
    // Work out port
    if (defined('SSL_ONLY') && SSL_ONLY) {
        $port = 443;
        $method = 'https';
    } elseif (defined('RPC_PORT')) {
        $port = RPC_PORT;
        if (defined('RPC_SSL') && RPC_SSL) {
            $method = 'https';
            if (!defined('RPC_PORT')) {
                $port = 443;
            }
        }
    } else {
        $port = 80;
    }
    $client = new xmlrpc_client(constant('RPC_RELATIVE_PATH') . '/rpcserver.php', $host, $port);
    if (DEBUG) {
        $client->setDebug(1);
    }
    $client->setSSLVerifyPeer(0);
    $client->setSSLVerifyHost(0);

    return $client->send($msg, 0, $method);
}

/**
 * @param $user
 * @param $hash
 */
function updateUserPasswordHash($user, $hash)
{
    $sqlCheckLenght = "SELECT CHARACTER_MAXIMUM_LENGTH AS passwordfieldlength FROM information_schema.columns WHERE column_name = 'password' AND table_name = 'users'";
    $passwordFiledLengthResult = dbquery($sqlCheckLenght);
    $passwordFiledLength = (int)Db::mysqli_result($passwordFiledLengthResult, 0, 'passwordfieldlength');

    if ($passwordFiledLength < 255) {
        $sqlUpdateFieldLength = 'ALTER TABLE `users` CHANGE `password` `password` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL';
        dbquery($sqlUpdateFieldLength);
        audit_log(sprintf(__('auditlogquareleased03', true) . ' ', $passwordFiledLength));
    }

    $sqlUpdateHash = "UPDATE `users` SET `password` = '$hash' WHERE `users`.`username` = '$user'";
    dbquery($sqlUpdateHash);
    audit_log(__('auditlogupdateuser03', true) . ' ' . $user);
}

/**
 * @param string $username username that should be checked if it exists
 * @return boolean true if user exists, else false
 */
function checkForExistingUser($username)
{
    $sqlQuery = "SELECT COUNT(username) AS counter FROM users WHERE username = '" .  \MailWatch\Sanitize::safe_value($username) . "'";
    $row = dbquery($sqlQuery)->fetch_object();

    return $row->counter > 0;
}

/**
 * @return array
 */
function checkConfVariables()
{
    $needed = [
        'ALLOWED_TAGS',
        'AUDIT',
        'AUDIT_DAYS_TO_KEEP',
        'AUTO_RELEASE',
        'DATE_FORMAT',
        'DB_DSN',
        'DB_HOST',
        'DB_NAME',
        'DB_PASS',
        'DB_TYPE',
        'DB_USER',
        'DEBUG',
        'DISPLAY_IP',
        'DISTRIBUTED_SETUP',
        'DOMAINADMIN_CAN_RELEASE_DANGEROUS_CONTENTS',
        'DOMAINADMIN_CAN_SEE_DANGEROUS_CONTENTS',
        'FILTER_TO_ONLY',
        'FROMTO_MAXLEN',
        'HIDE_HIGH_SPAM',
        'HIDE_NON_SPAM',
        'HIDE_UNKNOWN',
        'IMAGES_DIR',
        'LANG',
        'LDAP_DN',
        'LDAP_EMAIL_FIELD',
        'LDAP_FILTER',
        'LDAP_HOST',
        'LDAP_MS_AD_COMPATIBILITY',
        'LDAP_PASS',
        'LDAP_PORT',
        'LDAP_PROTOCOL_VERSION',
        'LDAP_USER',
        'LDAP_USERNAME_FIELD',
        'LISTS',
        'MAIL_LOG',
        'MAILWATCH_HOME',
        'MAILWATCH_MAIL_HOST',
        'MAILWATCH_MAIL_PORT',
        'MAILWATCH_FROM_ADDR',
        'MAILWATCH_HOSTURL',
        'MAX_RESULTS',
        'MEMORY_LIMIT',
        'MS_CONFIG_DIR',
        'MS_EXECUTABLE_PATH',
        'MS_LIB_DIR',
        'MS_LOG',
        'MS_SHARE_DIR',
        'MSRE',
        'MSRE_RELOAD_INTERVAL',
        'MSRE_RULESET_DIR',
        'MW_LOGO',
        'PROXY_PASS',
        'PROXY_PORT',
        'PROXY_SERVER',
        'PROXY_USER',
        'QUARANTINE_DAYS_TO_KEEP',
        'QUARANTINE_FILTERS_COMBINED',
        'QUARANTINE_MSG_BODY',
        'QUARANTINE_REPORT_DAYS',
        'QUARANTINE_REPORT_FROM_NAME',
        'QUARANTINE_REPORT_SUBJECT',
        'QUARANTINE_SENDMAIL_PATH',
        'QUARANTINE_SUBJECT',
        'QUARANTINE_USE_FLAG',
        'QUARANTINE_USE_SENDMAIL',
        'RECORD_DAYS_TO_KEEP',
        'RESOLVE_IP_ON_DISPLAY',
        'RPC_ALLOWED_CLIENTS',
        'RPC_ONLY',
        'RPC_RELATIVE_PATH',
        'SA_DIR',
        'SA_MAXSIZE',
        'SA_PREFS',
        'SA_RULES_DIR',
        'SHOW_DOC',
        'SHOW_MORE_INFO_ON_REPORT_GRAPH',
        'SHOW_SFVERSION',
        'SSL_ONLY',
        'STATUS_REFRESH',
        'STRIP_HTML',
        'SUBJECT_MAXLEN',
        'TEMP_DIR',
        'TIME_FORMAT',
        'TIME_ZONE',
        'USE_LDAP',
        'USE_PROXY',
        'VIRUS_INFO',
        'DISPLAY_VIRUS_REPORT',
    ];

    $obsolete = [
        'MS_LOGO',
        'QUARANTINE_MAIL_HOST',
        'QUARANTINE_MAIL_PORT',
        'QUARANTINE_FROM_ADDR',
        'QUARANTINE_REPORT_HOSTURL',
        'CACHE_DIR',
        'LDAP_SSL',
        'TTF_DIR',
        'PROXY_TYPE',
    ];

    $optional = [
        'RPC_PORT' => ['description' => 'needed if RPC_ONLY mode is enabled'],
        'RPC_SSL' => ['description' => 'needed if RPC_ONLY mode is enabled'],
        'RPC_REMOTE_SERVER' => ['description' => 'needed to show number of mails in postfix queues on remote server (RPC)'],
        'VIRUS_REGEX' => ['description' => 'needed in distributed setup'],
        'LDAP_BIND_PREFIX' => ['description' => 'needed when using LDAP authentication'],
        'LDAP_BIND_SUFFIX' => ['description' => 'needed when using LDAP authentication'],
        'EXIM_QUEUE_IN' => ['description' => 'needed only if using Exim as MTA'],
        'EXIM_QUEUE_OUT' => ['description' => 'needed only if using Exim as MTA'],
        'PWD_RESET_FROM_NAME' => ['description' => 'needed if Password Reset feature is enabled'],
        'PWD_RESET_FROM_ADDRESS' => ['description' => 'needed if Password Reset feature is enabled'],
        'MAILQ' => ['description' => 'needed when using Exim or Sendmail to display the inbound/outbound mail queue lengths'],
        'MAIL_SENDER' => ['description' => 'needed if you use Exim or Sendmail Queue'],
        'SESSION_NAME' => ['description' => 'needed if experiencing session conflicts'],
        'SENDMAIL_QUEUE_IN' => ['description' => 'needed only if using Sendmail as MTA'],
        'SENDMAIL_QUEUE_OUT' => ['description' => 'needed only if using Sendmail as MTA'],
        'USER_SELECTABLE_LANG' => ['description' => 'comma separated list of codes for languages the users can use eg. "de,en,fr,it,ja,nl,pt_br"'],
        'MAILWATCH_SMTP_HOSTNAME' => ['description' => 'needed only if you use a remote SMTP server to send MailWatch emails'],
        'SESSION_TIMEOUT' => ['description' => 'needed if you want to override the default session timeout'],
        'STATUSGRAPH_INTERVAL' => ['description' => 'to change the interval of the status chart (default 60 minutes)'],
        'ALLOW_NO_USER_DOMAIN' => ['description' => 'allow usernames not in mail format for domain admins and regular users'],
        'ENABLE_SUPER_DOMAIN_ADMINS' => ['description' => 'allows domain admins to change domain admins from the same domain'],
        'USE_IMAP' => ['description' => 'use IMAP for user authentication'],
        'IMAP_HOST' => ['description' => 'IMAP host to be used for user authentication'],
        'IMAP_AUTOCREATE_VALID_USER' => ['description' => 'enable to autorcreate user from valid imap login']
    ];

    $results = [];
    $neededMissing = [];
    foreach ($needed as $item) {
        if (!defined($item)) {
            $neededMissing[] = $item;
        }
    }
    $results['needed']['count'] = count($neededMissing);
    $results['needed']['list'] = $neededMissing;

    $obsoleteStillPresent = [];
    foreach ($obsolete as $item) {
        if (defined($item)) {
            $obsoleteStillPresent[] = $item;
        }
    }
    $results['obsolete']['count'] = count($obsoleteStillPresent);
    $results['obsolete']['list'] = $obsoleteStillPresent;

    $optionalMissing = [];
    foreach ($optional as $key => $item) {
        if (!defined($key)) {
            $optionalMissing[$key] = $item;
        }
    }
    $results['optional']['count'] = count($optionalMissing);
    $results['optional']['list'] = $optionalMissing;

    return $results;
}

/**
 * @param integer $lenght
 * @return string
 */
function get_random_string($lenght)
{
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes($lenght));
    }

    if (function_exists('mcrypt_create_iv')) {
        $random = mcrypt_create_iv($lenght, MCRYPT_DEV_URANDOM);
        if (false !== $random) {
            return bin2hex($random);
        }
    }

    if (DIRECTORY_SEPARATOR === '/' && @is_readable('/dev/urandom')) {
        // On unix system and if /dev/urandom is readable
        $handle = fopen('/dev/urandom', 'rb');
        $random = fread($handle, $lenght);
        fclose($handle);

        return bin2hex($random);
    }

    if (function_exists('openssl_random_pseudo_bytes')) {
        $random = openssl_random_pseudo_bytes($lenght);
        if (false !== $random) {
            return bin2hex($random);
        }
    }

    // if none of the above three secure functions are enabled use a pseudorandom string generator
    // note to sysadmin: check your php installation if the following code is executed and make your system secure!
    $random = '';
    $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $max = mb_strlen($keyspace, '8bit') - 1;
    for ($i = 0; $i < $lenght; ++$i) {
        $random .= $keyspace[mt_rand(0, $max)];
    }

    return $random;
}

/**
 * @param string $email
 * @param string $html
 * @param string $text
 * @param string $subject
 * @param bool $pwdreset
 * @return mixed
 */
function send_email($email, $html, $text, $subject, $pwdreset = false)
{
    $mime = new Mail_mime("\n");
    if ($pwdreset === true && (defined('PWD_RESET_FROM_NAME') && defined('PWD_RESET_FROM_ADDRESS') && PWD_RESET_FROM_NAME !== '' && PWD_RESET_FROM_ADDRESS !== '')) {
        $sender = PWD_RESET_FROM_NAME . '<' . PWD_RESET_FROM_ADDRESS . '>';
    } else {
        $sender = QUARANTINE_REPORT_FROM_NAME . ' <' . MAILWATCH_FROM_ADDR . '>';
    }
    $hdrs = [
        'From' => $sender,
        'To' => $email,
        'Subject' => $subject,
        'Date' => date('r')
    ];
    $mime_params = [
        'text_encoding' => '7bit',
        'text_charset' => 'UTF-8',
        'html_charset' => 'UTF-8',
        'head_charset' => 'UTF-8'
    ];
    $mime->addHTMLImage(MAILWATCH_HOME . '/' . IMAGES_DIR . MW_LOGO, 'image/png', MW_LOGO, true);
    $mime->setTXTBody($text);
    $mime->setHTMLBody($html);
    $body = $mime->get($mime_params);
    $hdrs = $mime->headers($hdrs);
    if (defined('MAILWATCH_SMTP_HOSTNAME')) {
        $mail_param = ['localhost' => MAILWATCH_SMTP_HOSTNAME, 'host' => MAILWATCH_MAIL_HOST, 'port' => MAILWATCH_MAIL_PORT];
    } else {
        $mail_param = ['host' => MAILWATCH_MAIL_HOST, 'port' => MAILWATCH_MAIL_PORT];
    }
    $mail = new Mail_smtp($mail_param);

    return $mail->send($email, $hdrs, $body);
}

/**
 * @param $ip
 * @param bool|string $net
 * @param bool|string $privateLocal
 * @return bool
 */
function ip_in_range($ip, $net = false, $privateLocal = false)
{
    if ($privateLocal === 'private') {
        $privateIPSet = new \IPSet\IPSet([
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
            'fc00::/7',
            'fe80::/10',
        ]);

        return $privateIPSet->match($ip);
    }

    if ($privateLocal === 'local') {
        $localIPSet = new \IPSet\IPSet([
            '127.0.0.0/8',
            '::1',
        ]);

        return $localIPSet->match($ip);
    }

    if ($privateLocal === false && $net !== false) {
        $network = new \IPSet\IPSet([
            $net
        ]);

        return $network->match($ip);
    }

    //return false to fail gracefully
    return false;
}

/**
 * @return string
 */
function generateToken()
{
    $tokenLenght = 32;

    return get_random_string($tokenLenght);
}

/**
 * @param string $token
 * @return boolean
 */
function checkToken($token)
{
    if (!isset($_SESSION['token'])) {
        return false;
    }

    return $_SESSION['token'] === \MailWatch\Sanitize::deepSanitizeInput($token, 'url');
}

/**
 * @param string $formstring
 * @return string
 */
function generateFormToken($formstring)
{
    if (!isset($_SESSION['token'])) {
        die(__('dietoken99'));
    }

    $calc = hash_hmac('sha256', $formstring . $_SESSION['token'], $_SESSION['formtoken']);

    return $calc;
}

/**
 * @param string $formstring
 * @param string $formtoken
 * @return bool
 */
function checkFormToken($formstring, $formtoken)
{
    if (!isset($_SESSION['token'], $_SESSION['formtoken'])) {
        return false;
    }
    $calc = hash_hmac('sha256', $formstring . $_SESSION['token'], $_SESSION['formtoken']);

    return $calc === \MailWatch\Sanitize::deepSanitizeInput($formtoken, 'url');
}

/**
 * Checks if the passed language code is allowed to be used for the users
 * @param string $langCode
 * @return boolean
 */
function checkLangCode($langCode)
{
    $validLang = explode(',', USER_SELECTABLE_LANG);
    $found = array_search($langCode, $validLang);
    if ($found === false || $found === null) {
        audit_log(sprintf(__('auditundefinedlang12', true), $langCode));
        return false;
    } else {
        return true;
    }
}

/**
 * Updates the user login expiry
 * @param string $myusername
 * @return boolean
 */
function updateLoginExpiry($myusername)
{
    $sql = "SELECT login_timeout FROM users WHERE username='" .  \MailWatch\Sanitize::safe_value($myusername) . "'";
    $result = dbquery($sql);

    if ($result->num_rows === 0) {
        // Something went wrong, or user no longer exists
        return false;
    }

    $login_timeout = Db::mysqli_result($result, 0, 'login_timeout');

    // Use global if individual value is disabled (-1)
    if ($login_timeout === '-1') {
        if (defined('SESSION_TIMEOUT')) {
            if (SESSION_TIMEOUT > 0 && SESSION_TIMEOUT <= 99999) {
                $expiry_val = (time() + SESSION_TIMEOUT);
            } else {
                $expiry_val = 0;
            }
        } else {
            $expiry_val = (time() + 600);
        }
        // If set, use the individual timeout
    } elseif ($login_timeout === '0') {
        $expiry_val = 0;
    } else {
        $expiry_val = (time() + (int)$login_timeout);
    }
    $sql = "UPDATE users SET login_expiry='" . $expiry_val . "', last_login='" . time() . "' WHERE username='" .  \MailWatch\Sanitize::safe_value($myusername) . "'";
    $result = dbquery($sql);

    return $result;
}

/**
 * Checks the user login expiry against the current time, if enabled
 * Returns true if expired
 * @param string $myusername
 * @return boolean
 */
function checkLoginExpiry($myusername)
{
    $sql = "SELECT login_expiry FROM users WHERE username='" .  \MailWatch\Sanitize::safe_value($myusername) . "'";
    $result = dbquery($sql);

    if ($result->num_rows === 0) {
        // Something went wrong, or user no longer exists
        return true;
    }

    $login_expiry = Db::mysqli_result($result, 0, 'login_expiry');

    if ($login_expiry === '-1') {
        // User administratively logged out
        return true;
    } elseif ($login_expiry === '0') {
        // Login never expires, so just return false
        return false;
    } elseif ((int)$login_expiry > time()) {
        // User is active
        return false;
    } else {
        // User has timed out
        return true;
    }
}

/**
 * Checks for a privilege change, returns true if changed
 * @param string $myusername
 * @return boolean
 */
function checkPrivilegeChange($myusername)
{
    $sql = "SELECT type FROM users WHERE username='" .  \MailWatch\Sanitize::safe_value($myusername) . "'";
    $result = dbquery($sql);

    if ($result->num_rows === 0) {
        // Something went wrong, or user does not exist
        return true;
    }

    $user_type = Db::mysqli_result($result, 0, 'type');

    if ($_SESSION['user_type'] !== $user_type) {
        // Privilege change detected
        return true;
    }

    return false;
}
