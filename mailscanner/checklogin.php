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

if (isset($_SERVER['PHP_AUTH_USER'])) {
    $myusername = $_SERVER['PHP_AUTH_USER'];
    $mypassword = $_SERVER['PHP_AUTH_PW'];
} else {
    // Define $myusername and $mypassword
    $myusername = $_POST['myusername'];
    $mypassword = $_POST['mypassword'];
}

if ((USE_LDAP == 1) && (($result = ldap_authenticate($myusername, $mypassword)) != null)) {
    $_SESSION['user_ldap'] = '1';
    $myusername = safe_value($result);
    $sql = "SELECT * FROM users WHERE username='$myusername'";
} else {
    $myusername = safe_value($myusername);
    if ($mypassword != "") {
        $mypassword = safe_value($mypassword);
        $encrypted_mypassword = md5($mypassword);
    }
    $sql = "SELECT * FROM users WHERE username='$myusername' and password='$encrypted_mypassword'";
}

$result = dbquery($sql);

if (!$result) {
    $message = 'Invalid query: ' . mysql_error() . "\n";
    $message .= 'Whole query: ' . $sql;
    die($message);
}
if (mysql_num_rows($result) > 0) {
    $fullname = mysql_result($result, 0, 'fullname');
    $usertype = mysql_result($result, 0, 'type');
}
$sql1 = "SELECT filter FROM user_filters WHERE username='$myusername' AND active='Y'";
$result1 = dbquery($sql1);

if (!$result1) {
    $message = 'Invalid query: ' . mysql_error() . "\n";
    $message .= 'Whole query: ' . $sql1;
    die($message);
}

$filter[] = $myusername;
while ($row = mysql_fetch_array($result1)) {
    $filter[] = $row['filter'];
}

$global_filter = address_filter_sql($filter, $usertype);

switch ($usertype) {

    case "A":
        $global_list = "1=1";
        break;
    case "D":
        if (strpos($myusername, '@')) {
            $ar = explode("@", $myusername);
            $domainname = $ar[1];
            if ((defined('FILTER_TO_ONLY') & FILTER_TO_ONLY)) {
                $global_filter = $global_filter . " OR to_domain='$domainname'";
            } else {
                $global_filter = $global_filter . " OR to_domain='$domainname' OR from_domain='$domainname'";
            }
            $global_list = "to_domain='$domainname'";
        } else {
            $global_list = "to_address='$myusername'";
            foreach ($filter as $to_address) {
                $global_list .= " OR to_address='$to_address'";
            }
        }
        break;
    case "U":
        $global_list = "to_address='$myusername'";
        foreach ($filter as $to_address) {
            $global_list .= " OR to_address='$to_address'";
        }
        break;
}

$global_filter = '(' . $global_filter . ')';

// Mysql_num_row is counting table row
$count = mysql_num_rows($result);

// If result matched $myusername and $mypassword, table row must be 1 row

if ($count == 1) {
    // Register $myusername, $mypassword and redirect to file "login_success.php"
    $_SESSION['myusername'] = $myusername;
    $_SESSION['fullname'] = $fullname;
    $_SESSION['user_type'] = $usertype;
    $_SESSION['domain'] = $domainname;
    $_SESSION['global_filter'] = $global_filter;
    $_SESSION['global_list'] = $global_list;
    $_SESSION['global_array'] = $filter;
    header("Location: index.php");
} else {

    echo '<html>';
    echo '<head>';
    echo '<link rel="shortcut icon" href="images/favicon.png" >' . "\n";
    echo '<title>MailWatch Login Page</title>';
    echo '</head>';
    echo '<body>';
    echo '<table width="300" border="0" align="center" cellpadding="0" cellspacing="1" bgcolor="#CCCCCC">';
    echo '<TR>';

    echo '<td align="center"><img src="images/mailwatch-logo.png"></td>';
    echo '</tr>';

    echo '<tr>';

    echo '<form name="form1" method="post" action="checklogin.php">';

    echo '<td>';

    echo '<table width="100%" border="0" cellpadding="3" cellspacing="1" bgcolor="#FFFFFF">';

    echo '<tr>';
    echo '    <td colspan="3"><strong>MailWatch Login</strong></td>';
    echo '</tr>';

    echo '<tr>';
    echo '    <td colspan="3"> Bad username or Password</td>';
    echo '</tr>';

    echo '<tr>';
    echo '    <td width="78">Username</td>';
    echo '    <td width="6">:</td>';
    echo '    <td width="294"><input name="myusername" type="text" id="myusername"></td>';
    echo '</tr>';

    echo '<tr>';
    echo '    <td>Password</td>';
    echo '    <td>:</td>';
    echo '    <td><input name="mypassword" type="password" id="mypassword"></td>';
    echo '</tr>';

    echo '<tr>';
    echo '    <td>&nbsp;</td>';
    echo '    <td>&nbsp;</td>';
    echo '    <td><input type="submit" name="Submit" value="Login"> <input type="reset" value="Reset">  <INPUT TYPE="button" VALUE="Back" onClick="history.go(-1);return true;"></td>';
    echo '</tr>
</table>
</td>
</form>
</tr>
</table>
</body>
</html>';
}

// close any DB connections
dbclose();
