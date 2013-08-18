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

function  postfixinq()
  {
  $handle = opendir('/var/spool/postfix/hold/');
  $inq = 0;
  while (false !== ($file = readdir($handle)))
    {
    //evaluate each entry, removing the . & .. entries
    if ($file !== '.' && $file !== '..')
      {
      $inq++;
      }
    }
  closedir($handle);
  return $inq;
  }

function  postfixallq()
  {
  $last_line = exec('mailq');
  $pos = strpos($last_line, 'in ');
  $start = substr($last_line, $pos+3);
  $allq = intval($start);
  return $allq;
  }

function  postfixmailq()
  {
  $last_line = exec('mailq', $output);
  while( list(,$row) = each($output) )
    {
    echo $row ."\n";
    }
  }
?>
