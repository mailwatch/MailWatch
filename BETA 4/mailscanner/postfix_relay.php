<?php

/*
 MailWatch for MailScanner
 Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)

 adapted for Postfix from original sendmail_relay.php by Kai Schaetzl, 12/2011
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
require("functions.php");

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

  // Parse the date, time, host, process pid and log entry 04CF7F970F
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

class postfix_parser {
 var $raw;
 var $id;
 var $entry;
 var $entries;

 function postfix_parser($line) {
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

function doit($input)
{
 global $fp;
 if(!$fp = popen($input,'r')) {
  die("Cannot open pipe"); 
 }
 
 $lines = 1;
 while($line = fgets($fp,2096)) {
  // Reset variables
  unset($parsed, $postfix, $_timestamp, $_host, $_type, $_msg_id, $_relay, $_dsn, $_status, $_delay);
 
  $parsed = new syslog_parser($line);
  $_timestamp = mysql_real_escape_string($parsed->timestamp);
  $_host = mysql_real_escape_string($parsed->host);
 
  // Postfix
  if($parsed->process == 'postfix/smtp' && class_exists('postfix_parser')) {
   $postfix = new postfix_parser($parsed->entry);
   if(DEBUG) { print_r($postfix); }
   $_msg_id = mysql_real_escape_string($postfix->id);

   // Milter-ahead rejections
   if((preg_match('/Milter: /i',$postfix->raw)) && (preg_match('/(rejected recipient|user unknown)/i',$postfix->entries['reject']))) {
    $_type = mysql_real_escape_string('unknown_user');
    $_status = mysql_real_escape_string(get_email($postfix->entries['to']));
   }

   // Unknown users
   if(preg_match('/user unknown/i',$postfix->entry)) {
    // Unknown users
    $_type = mysql_real_escape_string('unknown_user');
    $_status = mysql_real_escape_string($postfix->raw);
   }
	 
	 // you can use these matches to populate your table with all the various reject reasons etc., so one could get stats about MTA rejects as well
	 // example 
	 if(preg_match('/NOQUEUE/i',$postfix->entry))
	 {
    $_status = mysql_real_escape_string($postfix->raw);
		if(preg_match('/Client host rejected: cannot find your hostname/i',$postfix->entry)) {
			$_type = mysql_real_escape_string('unknown_hostname');
		}
		else
			$_type = mysql_real_escape_string('NOQUEUE');
		$_status = mysql_real_escape_string($postfix->raw);
	 }
   // Relay lines
   if(isset($postfix->entries['relay']) && isset($postfix->entries['status'])) {
    $_type = mysql_real_escape_string('relay');
    $_delay = mysql_real_escape_string($postfix->entries['delay']);
    $_relay = mysql_real_escape_string(get_ip($postfix->entries['relay']));
    $_dsn = mysql_real_escape_string($postfix->entries['dsn']);
    $_status = mysql_real_escape_string($postfix->entries['status']);
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
 doit('cat '.MAIL_LOG);
} else {
 // Refresh first
 doit('cat '.MAIL_LOG);
 // Start watching the maillog
 doit('tail -F -n0 '.MAIL_LOG);
}

?>
