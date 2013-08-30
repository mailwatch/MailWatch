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

echo '<!doctype html public "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">'."\n";
echo '<html>'."\n";
echo '<head>'."\n";
echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">'."\n";

if(file_exists('conf.php')){
?>
<style type="text/css"> 
  table.center {
    margin-left:auto; 
    margin-right:auto;
  }
</style>
<title>MailWatch Login Page</title>
</head>
<table width="300" border="1" class="center" cellpadding="0" cellspacing="0">
    <tr>

         <td align="center"><img src="images/mailwatch-logo.png" alt="Mailwatch Logo"></td>
        </tr>

<tr>
<td>
<form name="form1" method="post" action="checklogin.php">
<table width="100%" border="0" cellpadding="3" cellspacing="1">
<tr>
<td colspan="3"><strong> MailWatch Login</strong></td>
</tr>
<tr>
<td style="width:78px;">Username</td>
<td style="width:6px;">:</td>
<td style="width:294px;"><input name="myusername" type="text" id="myusername"></td>
</tr>
<tr>
<td>Password</td>
<td>:</td>
<td><input name="mypassword" type="password" id="mypassword"></td>
</tr>
<tr>
<td>&nbsp;</td>
<td>&nbsp;</td>
<td><input type="submit" name="Submit" value="Login"> <input type="reset" value="Reset">  <INPUT TYPE="button" VALUE="Back" onClick="history.go(-1);return true;"></td>
</tr>
</table>
</form>
</td>
</tr>
</table>
</html>
<?php
}
else{
?>

<title>MailWatch Login Page</title>
</head>
<table width="300" border="1" style="text-align:center;" cellpadding="0" cellspacing="0">
    <tr>

         <td align="center"><img src="images/mailwatch-logo.png" alt="MailWatch"></td>
        </tr>

<tr>
<td>
<form name="form1" method="post" action="checklogin.php">
<table width="100%" border="0" cellpadding="3" cellspacing="1">
<tr>
<td colspan="3"><strong> MailWatch Login</strong></td>
</tr>
<tr>
    <td colspan="3"> Sorry the Server is missing conf.php. Please create the file by copying conf.php.example and making the required changes.</td>
</tr>
<tr>
<td>&nbsp;</td>
<td>&nbsp;</td>
<td><input type="button" value="Back" onClick="history.go(-1);return true;"></td>
</tr>
</table>
</form>
</td>

</tr>
</table>
</html>
<?php
}
