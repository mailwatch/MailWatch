<?php

/*
 * MailWatch for MailScanner
 * Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 * Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 * Copyright (C) 2014-2018  MailWatch Team (https://github.com/mailwatch/1.2.0/graphs/contributors)
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

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/lib/password.php';
require_once __DIR__ . '/lib/hash_equals.php';
disableBrowserCache();

if (isset($_POST['token'])) {
    if (!isset($_SESSION['token'])) {
        //login page timed out and session for token is not valid anymore
        header('Location: login.php?error=pagetimeout');
        die();
    }

    if (false === checkToken($_POST['token'])) {
        header('Location: login.php?error=pagetimeout');
        die();
    }
}
$_SESSION['token'] = generateToken();

if (isset($_SERVER['PHP_AUTH_USER'])) {
    $myusername = $_SERVER['PHP_AUTH_USER'];
    $mypassword = $_SERVER['PHP_AUTH_PW'];
} else {
    // Define $myusername and $mypassword
    if (!isset($_POST['myusername'], $_POST['mypassword'])) {
        header('Location: login.php?error=baduser');
        logFailedLogin();
        die();
    }
    $myusername = html_entity_decode($_POST['myusername']);
    $mypassword = $_POST['mypassword'];
}

$_SESSION['user_ldap'] = false;
$_SESSION['user_imap'] = false;
if (defined('USE_LDAP') &&
    (USE_LDAP === true) &&
    (($result = ldap_authenticate($myusername, $mypassword)) !== null)
) {
    $_SESSION['user_ldap'] = true;
    $myusername = safe_value($result);
    $mypassword = safe_value($mypassword);
} elseif (
    defined('USE_IMAP') &&
    (USE_IMAP === true) &&
    (($result = imap_authenticate($myusername, $mypassword)) !== null)
) {
    $_SESSION['user_imap'] = true;
    $myusername = safe_value($myusername);
    $mypassword = safe_value($mypassword);
} else {
    if ($mypassword !== '') {
        $myusername = safe_value($myusername);
        $mypassword = safe_value($mypassword);
    } else {
        header('Location: login.php?error=emptypassword');
        logFailedLogin($myusername);
        die();
    }
}

$sql = "SELECT * FROM users WHERE username='$myusername'";
$result = dbquery($sql);

// mysql_num_row is counting table row
$usercount = $result->num_rows;
if ($usercount === 0) {
    //no user found, redirect to login
    dbclose();
    header('Location: login.php?error=baduser');
    logFailedLogin($myusername);
    die();
}

if (
    ($_SESSION['user_ldap'] === false) &&
    ($_SESSION['user_imap'] === false)
) {
    $passwordInDb = database::mysqli_result($result, 0, 'password');
    if (!password_verify($mypassword, $passwordInDb)) {
        if (!hash_equals(md5($mypassword), $passwordInDb)) {
            header('Location: login.php?error=baduser');
            logFailedLogin($myusername);
            die();
        }

        $newPasswordHash = password_hash($mypassword, PASSWORD_DEFAULT);
        updateUserPasswordHash($myusername, $newPasswordHash);
    } else {
        // upgraded password is valid, continue as normal
        if (password_needs_rehash($passwordInDb, PASSWORD_DEFAULT)) {
            $newPasswordHash = password_hash($mypassword, PASSWORD_DEFAULT);
            updateUserPasswordHash($myusername, $newPasswordHash);
        }
    }
}

$fullname = database::mysqli_result($result, 0, 'fullname');
$usertype = database::mysqli_result($result, 0, 'type');

$sql_userfilter = "SELECT filter FROM user_filters WHERE username='$myusername' AND active='Y'";
$result_userfilter = dbquery($sql_userfilter);

$filter[] = $myusername;
while ($row = $result_userfilter->fetch_array()) {
    $filter[] = $row['filter'];
}

$global_filter = address_filter_sql($filter, $usertype);

switch ($usertype) {
    case 'A':
        $global_list = '1=1';
        break;
    case 'D':
        if (strpos($myusername, '@')) {
            $ar = explode('@', $myusername);
            $domainname = $ar[1];
            if (defined('FILTER_TO_ONLY') && FILTER_TO_ONLY) {
                $global_filter .= " OR to_domain='$domainname'";
            } else {
                $global_filter .= " OR to_domain='$domainname' OR from_domain='$domainname'";
            }
            $global_list = "to_domain='$domainname'";
            foreach ($filter as $to_domain) {
                if ($to_domain !== $myusername) {
                    $global_list .= " OR to_domain='$to_domain'";
                }
            }
        } else {
            $global_list = "to_address='$myusername'";
            foreach ($filter as $to_address) {
                $global_list .= " OR to_address='$to_address'";
            }
        }
        break;
    case 'U':
        $global_list = "to_address='$myusername'";
        foreach ($filter as $to_address) {
            $global_list .= " OR to_address='$to_address'";
        }
        break;
}

// If result matched $myusername and $mypassword, table row must be 1 row
if ($usercount === 1) {
    session_regenerate_id(true);
    // Register $myusername, $mypassword and redirect to file "login_success.php"
    $_SESSION['myusername'] = $myusername;
    $_SESSION['fullname'] = $fullname;
    $_SESSION['user_type'] = (isset($usertype) ? $usertype : '');
    $_SESSION['domain'] = (isset($domainname) ? $domainname : '');
    $_SESSION['global_filter'] = '(' . $global_filter . ')';
    $_SESSION['global_list'] = (isset($global_list) ? $global_list : '');
    $_SESSION['global_array'] = $filter;
    $_SESSION['token'] = generateToken();
    $_SESSION['formtoken'] = generateToken();
    // Initialize login expiry in users table for newly logged in user
    updateLoginExpiry($myusername);
    $redirect_url = 'index.php';
    if (isset($_SESSION['REQUEST_URI'])) {
        $redirect_url = $_SESSION['REQUEST_URI'];
        unset($_SESSION['REQUEST_URI']);
    }
    header('Location: ' . str_replace('&amp;', '&', sanitizeInput($redirect_url)));
} else {
    header('Location: login.php?error=baduser');
    logFailedLogin($myusername);
}

// close any DB connections
dbclose();
