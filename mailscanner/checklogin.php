<?php

/*
 * MailWatch for MailScanner
 * Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 * Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 * Copyright (C) 2014-2021  MailWatch Team (https://github.com/mailwatch/1.2.0/graphs/contributors)
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
disableBrowserCache();

if (isset($_POST['token'])) {
    if (!isset($_SESSION['token'])) {
        // login page timed out and session for token is not valid anymore
        header('Location: /login.php?error=pagetimeout');
        exit;
    }

    if (false === checkToken($_POST['token'])) {
        header('Location: /login.php?error=pagetimeout');
        exit;
    }
}
$_SESSION['token'] = generateToken();

if (isset($_SERVER['PHP_AUTH_USER'])) {
    $myusername = $_SERVER['PHP_AUTH_USER'];
    $mypassword = $_SERVER['PHP_AUTH_PW'];
} else {
    // Define $myusername and $mypassword
    if (!isset($_POST['myusername'], $_POST['mypassword'])) {
        header('Location: /login.php?error=baduser');
        logFailedLogin();
        exit;
    }
    $myusername = html_entity_decode((string)$_POST['myusername']);
    $mypassword = $_POST['mypassword'];
}

try {
    $authenticated = \MailWatch\ApplicationFactory::userAuthentication()->authenticate(
        (string)$myusername,
        (string)$mypassword,
        time(),
    );
} catch (\MailWatch\Users\Application\EmptyPassword) {
    header('Location: /login.php?error=emptypassword');
    logFailedLogin($myusername);
    exit;
} catch (\MailWatch\Users\Application\InvalidCredentials) {
    header('Location: /login.php?error=baduser');
    logFailedLogin($myusername);
    exit;
} catch (\MailWatch\Users\Application\AuthenticationProviderUnavailable $exception) {
    exit($exception->getMessage());
}

$account = $authenticated->account;
$myusername = $account->username;
$fullname = $account->fullName;
$usertype = $account->role;
$_SESSION['user_ldap'] = \MailWatch\Users\Domain\AuthenticationSource::Ldap === $authenticated->source;
$_SESSION['user_imap'] = \MailWatch\Users\Domain\AuthenticationSource::Imap === $authenticated->source;
if ($authenticated->passwordHashUpgraded) {
    audit_log(__('auditlogupdateuser03', true) . ' ' . $myusername);
}

$filter = array_map(
    static fn(string $value): string => safe_value($value),
    [$myusername, ...$account->activeFilters],
);

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

session_regenerate_id(true);
$_SESSION['myusername'] = $myusername;
$_SESSION['fullname'] = $fullname;
$_SESSION['user_type'] = $usertype;
$_SESSION['domain'] = ($domainname ?? '');
$_SESSION['global_filter'] = '(' . $global_filter . ')';
$_SESSION['global_list'] = ($global_list ?? '');
$_SESSION['global_array'] = $filter;
$_SESSION['token'] = generateToken();
$_SESSION['formtoken'] = generateToken();
$redirect_url = 'index.php';
if (isset($_SESSION['REQUEST_URI'])) {
    $redirect_url = $_SESSION['REQUEST_URI'];
    unset($_SESSION['REQUEST_URI']);
}
header('Location: ' . str_replace('&amp;', '&', sanitizeInput($redirect_url)));

// close any DB connections
dbclose();
