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
        $sql = "SELECT * FROM users WHERE username='$myusername' and password='$encrypted_mypassword'";
    } else {
        header("Location: login.php?error=emptypassword");
        die();
    }
}

$result = dbquery($sql);

if (!$result) {
    $message = 'Invalid query: ' . mysql_error() . "\n";
    $message .= 'Whole query: ' . $sql;
    die($message);
}

// mysql_num_row is counting table row
$usercount = mysql_num_rows($result);
if ($usercount == 0) {
    //no user found, redirect to login
    dbclose();
    header("Location: login.php?error=baduser");
} else {
    $fullname = mysql_result($result, 0, 'fullname');
    $usertype = mysql_result($result, 0, 'type');

    $sql_userfilter = "SELECT filter FROM user_filters WHERE username='$myusername' AND active='Y'";
    $result_userfilter = dbquery($sql_userfilter);

    if (!$result_userfilter) {
        $message = 'Invalid query: ' . mysql_error() . "\n";
        $message .= 'Whole query: ' . $sql_userfilter;
        die($message);
    }

    $filter[] = $myusername;
    while ($row = mysql_fetch_array($result_userfilter)) {
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
                if ((defined('FILTER_TO_ONLY') && FILTER_TO_ONLY)) {
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

    // If result matched $myusername and $mypassword, table row must be 1 row
    if ($usercount == 1) {
        // Register $myusername, $mypassword and redirect to file "login_success.php"
        $_SESSION['myusername'] = $myusername;
        $_SESSION['fullname'] = $fullname;
        $_SESSION['user_type'] = (isset($usertype) ? $usertype : '');
        $_SESSION['domain'] = (isset($domainname) ? $domainname : '');
        $_SESSION['global_filter'] = '(' . $global_filter . ')';
        $_SESSION['global_list'] = (isset($global_list) ? $global_list : '');
        $_SESSION['global_array'] = $filter;
        header("Location: index.php");
    } else {
        header("Location: login.php?error=baduser");
    }

    // close any DB connections
    dbclose();
}