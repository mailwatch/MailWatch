#!/usr/bin/php -q
<?php
/*
 MailWatch for MailScanner
 Copyright (C) 2003  Steve Freegard (smf@f2s.com)
fix for the id= issue 09.12.2011 by Kai Schaetzl

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

ini_set('error_log','syslog');
ini_set('html_errors','off');
ini_set('display_errors','on');
ini_set('implicit_flush','false');

// Edit this to reflect the full path to functions.php
require("/var/www/html/mailscanner/functions.php");

// Set-up environment
set_time_limit(0);

class syslog_parser {
 var $raw;
 var $timestamp;
 var $date;
 var $time;
 var $rfctime;
 var $host;
 var $process;
 var $pid;
 var $entry;
 var $months = array('Jan' => '1',
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
		     'Dec' => '12');

 function syslog_parser($line) {

  // Parse the date, time, host, process pid and log entry
  if(preg_match('/^(\S+)\s+(\d+)\s(\d+):(\d+):(\d+)\s(\S+)\s(\S+)\[(\d+)\]:\s(.+)$/',$line,$explode)) {

   // Store raw line
   $this->raw = $explode[0];
 
   // Decode the syslog time/date
   $month = $this->months[$explode[1]];
   $thismonth = date('n');
   $thisyear = date('Y');
   // Work out the year
   $year = $month <= $thismonth ? $thisyear : $thisyear - 1;
   $this->date = $explode[2].' '.$explode[1].' '.$year;
   $this->time = $explode[3].':'.$explode[4].':'.$explode[5];
   $datetime = $this->date.' '.$this->time;
   $this->timestamp = strtotime($datetime);
   $this->rfctime = date('r',$this->timestamp);

   $this->host = $explode[6];
   $this->process = $explode[7];
   $this->pid = $explode[8];
   $this->entry = $explode[9];
  } else { 
   return false;
  }
 }
}

class sendmail_parser {
 var $raw;
 var $id;
 var $entry;
 var $entries;

 function sendmail_parser($line) {
  $this->raw = $line;
  if(preg_match('/^(\S+):\s(.+)$/',$line,$match)) {
   $this->id = $match[1];
   
   // Milter
   if(preg_match('/(\S+):\sMilter:\s(.+)$/',$line,$milter)) {
    $match = $milter;
   }
    
   // Extract any key=value pairs
   if(strstr($match[2],'=')) {
    $items = explode(', ',$match[2]);
    $entries = array();
    foreach($items as $item) {
     $entry = explode('=',$item);
     $entries[$entry[0]] = $entry[1];
		 // fix for the id= issue 09.12.2011
		 if (isset($entry[2]))
			$entries[$entry[0]] = $entry[1].'='.$entry[2];
		 else
			$entries[$entry[0]] = $entry[1];
    }
    $this->entries = $entries;
   } else {
    $this->entry = $match[2];
   }
  } else {
   // No message ID found 
   // Extract any key=value pairs
   if(strstr($this->raw,'=')) {
    $items = explode(', ',$this->raw);
    $entries = array();
    foreach($items as $item) {
     $entry = explode('=',$item);
     $entries[$entry[0]] = $entry[1];
		 // fix for the id= issue 09.12.2011
		 if (isset($entry[2]))
			$entries[$entry[0]] = $entry[1].'='.$entry[2];
		 else
			$entries[$entry[0]] = $entry[1];
    }
    $this->entries = $entries;
   } else {
    return false;
   }
  } 
 }
 
}

function get_ip($line) {
 if(preg_match('/\[(\d+\.\d+\.\d+\.\d+)\]/',$line,$match)) {
  return $match[1];
 } else {
  return $line;
 }
}

function get_email($line) {
 if(preg_match('/<(\S+)>/',$line,$match)) {
  return $match[1];
 } else {
  return $line;
 }
}

function doit($input) {
 global $fp;
 if(!$fp = popen($input,'r')) {
  die("Cannot open pipe"); 
 }
 
 $lines = 1;
 while($line = fgets($fp,2096)) {
  // Reset variables
  unset($parsed, $sendmail, $_timestamp, $_host, $_type, $_msg_id, $_relay, $_dsn, $_status, $_delay);
 
  $parsed = new syslog_parser($line);
  $_timestamp = mysql_real_escape_string($parsed->timestamp);
  $_host = mysql_real_escape_string($parsed->host);
 
  // Sendmail
  if($parsed->process == 'sendmail' && class_exists('sendmail_parser')) {
   $sendmail = new sendmail_parser($parsed->entry);
   if(DEBUG) { print_r($sendmail); }
   
   $_msg_id = mysql_real_escape_string($sendmail->id);

   // Rulesets 
   if(isset($sendmail->entries['ruleset'])) {
    if($sendmail->entries['ruleset'] == 'check_relay') {
     // Listed in RBL(s)
     $_type = mysql_real_escape_string('rbl');
     $_relay = mysql_real_escape_string($sendmail->entries['arg2']);
     $_status = mysql_real_escape_string($sendmail->entries['reject']);
    }
    if($sendmail->entries['ruleset'] == 'check_mail') {
     // Domain does not resolve
     $_type = mysql_real_escape_string('unresolveable');
     $_status = mysql_real_escape_string(get_email($sendmail->entries['reject']));
    }
   }

   // Milter-ahead rejections
   if((preg_match('/Milter: /i',$sendmail->raw)) && (preg_match('/(rejected recipient|user unknown)/i',$sendmail->entries['reject']))) {
    $_type = mysql_real_escape_string('unknown_user');
    $_status = mysql_real_escape_string(get_email($sendmail->entries['to']));
   }

   // Unknown users
   if(preg_match('/user unknown/i',$sendmail->entry)) {
    // Unknown users
    $_type = mysql_real_escape_string('unknown_user');
    $_status = mysql_real_escape_string($sendmail->raw);
   }

   // Relay lines
   if(isset($sendmail->entries['relay']) && isset($sendmail->entries['stat'])) {
    $_type = mysql_real_escape_string('relay');
    $_delay = mysql_real_escape_string($sendmail->entries['xdelay']);
    $_relay = mysql_real_escape_string(get_ip($sendmail->entries['relay']));
    $_dsn = mysql_real_escape_string($sendmail->entries['dsn']);
    $_status = mysql_real_escape_string($sendmail->entries['stat']);
   }

  }
  if(isset($_type)) {
   dbquery("REPLACE INTO mtalog VALUES (FROM_UNIXTIME('$_timestamp'),'$_host','$_type','$_msg_id','$_relay','$_dsn','$_status','$_delay')");
  }
  $lines++;
 }
 pclose($fp);
}

if($_SERVER['argv'][1] == "--refresh") {
 doit('cat /var/log/maillog');
} else {
 // Refresh first
 doit('cat /var/log/maillog');
 // Start watching the maillog
 doit('tail -F -n0 /var/log/maillog');
}

?>
