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
 * As a special exception, you have permission to link this program with the JpGraph library and distribute executables,
 * as long as you follow the requirements of the GNU GPL in regard to all of the software in the executable aside from
 * JpGraph.
 *
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free
 * Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

ini_set('error_log', 'syslog');
ini_set('html_errors', 'off');
ini_set('display_errors', 'on');
ini_set('implicit_flush', 'false');

// Edit this to reflect the full path to functions.php
require '/var/www/html/mailscanner/functions.php';

// Set-up environment
set_time_limit(0);

class syslog_parser
{
    public $raw;
    public $timestamp;
    public $date;
    public $time;
    public $rfctime;
    public $host;
    public $process;
    public $pid;
    public $entry;
    public $months = array(
        'Jan' => '1',
        'Feb' => '2',
        'Mar' => '3',
        'Apr' => '4',
        'May' => '5',
        'Jun' => '6',
        'Jul' => '7',
        'Aug' => '8',
        'Sep' => '9',
        'Oct' => '10',
        'Nov' => '11',
        'Dec' => '12'
    );

    /**
     * @param string $line
     */
    public function __construct($line)
    {

        // Parse the date, time, host, process pid and log entry
        if (preg_match('/^(\S+)\s+(\d+)\s(\d+):(\d+):(\d+)\s(\S+)\s(\S+)\[(\d+)\]:\s(.+)$/', $line, $explode)) {
            // Store raw line
            $this->raw = $explode[0];

            // Decode the syslog time/date
            $month = $this->months[$explode[1]];
            $thismonth = date('n');
            $thisyear = date('Y');
            // Work out the year
            $year = $month <= $thismonth ? $thisyear : $thisyear - 1;
            $this->date = $explode[2] . ' ' . $explode[1] . ' ' . $year;
            $this->time = $explode[3] . ':' . $explode[4] . ':' . $explode[5];
            $datetime = $this->date . ' ' . $this->time;
            $this->timestamp = strtotime($datetime);
            $this->rfctime = date('r', $this->timestamp);

            $this->host = $explode[6];
            $this->process = $explode[7];
            $this->pid = $explode[8];
            $this->entry = $explode[9];
        } else {
            return false;
        }
    }
}

class sendmail_parser
{
    public $raw;
    public $id;
    public $entry;
    public $entries;

    /**
     * @param string $line
     */
    public function __construct($line)
    {
        $this->raw = $line;
        if (preg_match('/^(\S+):\s(.+)$/', $line, $match)) {
            $this->id = $match[1];

            // Milter
            if (preg_match('/(\S+):\sMilter:\s(.+)$/', $line, $milter)) {
                $match = $milter;
            }

            // Extract any key=value pairs
            if (strstr($match[2], '=')) {
                $items = explode(', ', $match[2]);
                $entries = array();
                foreach ($items as $item) {
                    $entry = explode('=', $item);
                    if (isset($entry[1])) {
                        $entries[$entry[0]] = $entry[1];
                        // fix for the id= issue 09.12.2011
                        if (isset($entry[2])) {
                            $entries[$entry[0]] = $entry[1] . '=' . $entry[2];
                        } else {
                            $entries[$entry[0]] = $entry[1];
                        }
                    }
                }
                $this->entries = $entries;
            } else {
                $this->entry = $match[2];
            }
        } else {
            // No message ID found
            // Extract any key=value pairs
            if (strstr($this->raw, '=')) {
                $items = explode(', ', $this->raw);
                $entries = array();
                foreach ($items as $item) {
                    $entry = explode('=', $item);
                    $entries[$entry[0]] = $entry[1];
                    // fix for the id= issue 09.12.2011
                    if (isset($entry[2])) {
                        $entries[$entry[0]] = $entry[1] . '=' . $entry[2];
                    } else {
                        $entries[$entry[0]] = $entry[1];
                    }
                }
                $this->entries = $entries;
            } else {
                return false;
            }
        }
    }
}

/**
 * @return string
 */
function get_ip($line)
{
    if (preg_match('/\[(\d+\.\d+\.\d+\.\d+)\]/', $line, $match)) {
        return $match[1];
    } else {
        return $line;
    }
}

/**
 * @return string
 */
function get_email($line)
{
    if (preg_match('/<(\S+)>/', $line, $match)) {
        return $match[1];
    } else {
        return $line;
    }
}

function doit($input)
{
    global $fp;
    if (!$fp = popen($input, 'r')) {
        die('Cannot open pipe');
    }

    $lines = 1;
    while ($line = fgets($fp, 2096)) {
        $parsed = new syslog_parser($line);
        $_timestamp = safe_value($parsed->timestamp);
        $_host = safe_value($parsed->host);
        $_dsn = '';
        $_delay = '';
        $_relay = '';

        // Sendmail
        if ($parsed->process === 'sendmail' && class_exists('sendmail_parser')) {
            $sendmail = new sendmail_parser($parsed->entry);
            if (true === DEBUG) {
                print_r($sendmail);
            }

            $_msg_id = safe_value($sendmail->id);

            // Rulesets
            if (isset($sendmail->entries['ruleset'])) {
                if ($sendmail->entries['ruleset'] === 'check_relay') {
                    // Listed in RBL(s)
                    $_type = safe_value('rbl');
                    $_relay = safe_value($sendmail->entries['arg2']);
                    $_status = safe_value($sendmail->entries['reject']);
                }
                if ($sendmail->entries['ruleset'] === 'check_mail') {
                    // Domain does not resolve
                    $_type = safe_value('unresolveable');
                    $_status = safe_value(get_email($sendmail->entries['reject']));
                }
            }

            // Milter-ahead rejections
            if (preg_match('/Milter: /i', $sendmail->raw) && preg_match(
                    '/(rejected recipient|user unknown)/i',
                    $sendmail->entries['reject']
                )
            ) {
                $_type = safe_value('unknown_user');
                $_status = safe_value(get_email($sendmail->entries['to']));
            }

            // Unknown users
            if (preg_match('/user unknown/i', $sendmail->entry)) {
                // Unknown users
                $_type = safe_value('unknown_user');
                $_status = safe_value($sendmail->raw);
            }

            // Relay lines
            if (isset($sendmail->entries['relay'], $sendmail->entries['stat'])) {
                $_type = safe_value('relay');
                $_delay = safe_value($sendmail->entries['xdelay']);
                $_relay = safe_value(get_ip($sendmail->entries['relay']));
                $_dsn = safe_value($sendmail->entries['dsn']);
                $_status = safe_value($sendmail->entries['stat']);
            }
        }
        if (isset($_type)) {
            dbquery(
                "REPLACE INTO mtalog VALUES (FROM_UNIXTIME('$_timestamp'),'$_host','$_type','$_msg_id','$_relay','$_dsn','$_status','$_delay')"
            );
        }
        $lines++;

        // Reset variables
        unset($line, $parsed, $sendmail, $_timestamp, $_host, $_type, $_msg_id, $_relay, $_dsn, $_status, $_delay);
    }
    pclose($fp);
}

if (isset($_SERVER['argv'][1]) && $_SERVER['argv'][1] === '--refresh') {
    doit('cat ' . MAIL_LOG);
} else {
    // Refresh first
    doit('cat ' . MAIL_LOG);
    // Start watching the maillog
    doit('tail -F -n0 ' . MAIL_LOG);
}
