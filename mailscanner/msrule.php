<?php

/*
 MailWatch for MailScanner
 Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)

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

require_once("./functions.php");

session_start();
require('login.function.php');

html_start("Rules");

# Stop anyone trying to read any other files
if (preg_match('/^'.preg_quote(MS_CONFIG_DIR,'/').'/',$_GET['file'])) {
 $file = preg_replace("/\.\./","",$_GET['file']);
}

echo '<table cellspacing="1" class="maildetail" width="100%">'."\n";
echo '<tr><td class="heading">File: '.$file.'</td></tr>'."\n";
echo '<tr><td><pre>'."\n";
if ($fh = @@fopen($file,'r')) {
 while (!feof($fh)) {
  $line = rtrim(fgets($fh,4096));
  if ($_GET['strip_comments']) {
   if (!preg_match('/^#/',$line) && !preg_match('/^$/',$line)) {
   echo $line."\n";
   }
  } else {
   echo $line."\n";
  }
 }
 fclose($fh);
} else {
 echo "Unable to open file.\n";
}
echo '</pre></td></tr>'."\n";
echo '</table>'."\n";

// Add the footer
html_end();
// close the connection to the Database
dbclose();
