#!/usr/bin/php -q
<?php

/*
 MailWatch for MailScanner
 Copyright (C) 2003  Steve Freegard (smf@f2s.com)

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

// Change the following to reflect the location of functions.php
require('/var/www/html/mailscanner/functions.php');
date_default_timezone_set (TIME_ZONE);

ini_set('error_log','syslog');
ini_set('html_errors','off');
ini_set('display_errors','on');
ini_set('implicit_flush','false');


function quarantine_reconcile() {
 $quarantine = get_conf_var('QuarantineDir');
 $d = dir($quarantine) or die($php_errormsg);
 while(false !== ($f = $d->read())) {
  if (preg_match('/^\d{8}$/',$f)) {
   if(is_array(($array = quarantine_list_dir($f)))) {
    foreach($array as $id) {
     dbg("Updating: $id");
     $sql = "UPDATE maillog SET timestamp=timestamp, quarantined=1 WHERE id='$id'";
     dbquery($sql);
    }
   }
  }
 }
}

function quarantine_clean() {
 $oldest = date('U',strtotime('-'.QUARANTINE_DAYS_TO_KEEP." days"));
 $quarantine = get_conf_var('QuarantineDir');
 
 $d = dir($quarantine) or die($php_errormsg);
 while (false !== ($f = $d->read())) {
  // Only interested in quarantine directories (yyyymmdd)
  if (preg_match('/^\d{8}$/',$f)) {
   $unixtime = quarantine_date_to_unixtime($f);
   if ($unixtime < $oldest) { 
    // Needs to be deleted
    $array = quarantine_list_dir($f);
    dbg("Processing directory $f: found ".count($array)." records to delete");
    foreach($array as $id) {
     // Update the quarantine flag
     $sql = "UPDATE maillog SET timestamp=timestamp, quarantined = NULL WHERE id='$id'";
     dbquery($sql);
    }
    dbg("Deleting: ".escapeshellarg($quarantine.'/'.$f));
    exec('rm -rf '.escapeshellarg($quarantine.'/'.$f),$output,$return);
    if($return > 0) {
     echo "Error: $output\n";
    }
   }
  }
 }
 $d->close();
} 

function quarantine_date_to_unixtime($dirname) {
 $y = substr($dirname, 0, 4);
 $m = substr($dirname, 4, 2);
 $d = substr($dirname, 6, 2);
 $unixtime = mktime(0,0,0,$m,$d,$y);
 return $unixtime;
}

function dbg($text) {
 if(DEBUG) {
  echo $text."\n";
 }
}

function quarantine_list_dir($dir) {
 $dir = get_conf_var('QuarantineDir')."/$dir";
 $spam = "$dir/spam";
 $nonspam = "$dir/nonspam";
 $mcp = "$dir/mcp"; 
 $array = array();

 if (is_dir($dir)) {
  // Main quarantine
  $d = dir($dir) or die($php_errormsg);
  while (false !== ($f = $d->read())) {
   if ($f != '.' && $f != '..' && $f != 'spam' && $f != 'nonspam' && $f != 'mcp') {
    //dbg("Found $dir/$f");
    $array[] = $f;
   }
  }
  $d->close();
 }

 if (is_dir($spam)) {
  // Spam folder
  $d = dir($spam) or die($php_errormsg);
  while (false !== ($f = $d->read())) {
   if ($f != '.' && $f != '..' && $f != 'spam' && $f != 'nonspam' && $f != 'mcp') {
    //dbg("Found $spam/$f");
    $array[] = $f;
   }
  }
  $d->close();
 }

 if (is_dir($nonspam)) {
  $d = dir($nonspam) or die($php_errormsg);
  while (false !== ($f = $d->read())) {
   if ($f != '.' && $f != '..' && $f != 'spam' && $f != 'nonspam' && $f != 'mcp') {
    //dbg("Found $nonspam/$f");
    $array[] = $f;
   }
  }
  $d->close();
 }

 if (is_dir($mcp)) {
  $d = dir($mcp) or die($php_errormsg);
  while (false !== ($f = $d->read())) {
   if ($f != '.' && $f != '..' && $f != 'spam' && $f != 'nonspam' && $f != 'mcp') {
    //dbg("Found $mcp/$f");
    $array[] = $f;
   }
  }
  $d->close();
 }

 return $array;
}

switch($_SERVER['argv'][1]) {
 case '--clean':
  quarantine_clean();
  break;
 case '--reconsile':
  // I really should learn to spell...
  quarantine_reconcile();
  break;
 case '--reconcile':
  quarantine_reconcile();
  break;
 default:
  die('Usage: '.$_SERVER['argv'][0].' [--clean] [--reconcile]'."\n");
  break;
}
 

?>
