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

/**
 * This script is a more verbose version of the ldap_authenticate function from functions.php from MW 1.2.7
 * which is used to login LDAP users.
 * It is intended to provide a way to debug ldap login problems.
 *
 * To use it set $username and $password below to credentials which you would use in the webinterface to
 * login to MW. If you change your MW location also adjust the "require ..." line to point to the functions.php
 */
$username='';
$password='';
require "/var/www/html/mailscanner/functions.php";
//uncomment the following line for more verbose output
//$verbose=true;

echo "Test connection to server" . PHP_EOL;
$ds = ldap_connect(LDAP_HOST, LDAP_PORT) or die("Connection to server failed");

$ldap_protocol_version = 3;
if (defined('LDAP_PROTOCOL_VERSION')) {
    $ldap_protocol_version = LDAP_PROTOCOL_VERSION;
}

  // Check if Microsoft Active Directory compatibility is enabled
        if (defined('LDAP_MS_AD_COMPATIBILITY') && LDAP_MS_AD_COMPATIBILITY === true) {
            echo "enable AD compatibility" . PHP_EOL;
            ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);
            $ldap_protocol_version = 3;
        }
        ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, $ldap_protocol_version);

        echo "Try authenticating as ". LDAP_USER . PHP_EOL;
        $bindResult = @ldap_bind($ds, LDAP_USER, LDAP_PASS);
        if (false === $bindResult) {
            die(ldap_print_error($ds));
        } else {
            echo "authentication for searching the account was successful" . PHP_EOL;
        }

        echo "search for $username in LDAP directory" . PHP_EOL;
        $ldap_search_results = ldap_search($ds, LDAP_DN, sprintf(LDAP_FILTER, $username)) or die("searching for accounts failed");
        echo "search done" . PHP_EOL;
        if (false === $ldap_search_results) {
            die("no valid result while searching for acccounts");
        }
        if (1 > ldap_count_entries($ds, $ldap_search_results)) {
            die("no results searching for accounts");
        }
        if (ldap_count_entries($ds, $ldap_search_results) > 1) {
            die("no accounts found matching the filter");
        } else {
            echo "found ". ldap_count_entries($ds, $ldap_search_results) . " accounts matching the filter" . PHP_EOL;
        }

        if ($ldap_search_results) {
            $result = ldap_get_entries($ds, $ldap_search_results) or die("getting account search results failed");
            ldap_free_result($ldap_search_results);
            if (isset($result[0])) {
                if (in_array('group', array_values($result[0]['objectclass']), true)) {
                    die("found ldap account is a group! won't login as group!!");
                }
                
                if (isset($verbose) && $verbose === true) {
                    var_dump($result);
                }
                    
                if (!isset($result[0][LDAP_USERNAME_FIELD], $result[0][LDAP_USERNAME_FIELD][0])) {
                    if (!isset($result[0][strtolower(LDAP_USERNAME_FIELD)], $result[0][strtolower(LDAP_USERNAME_FIELD)][0])) {
                        die("Use all lower case LDAP_USERNAME_FIELD!");
                    }
                    die("found ldap account object does not contain the username field: " . LDAP_USERNAME_FIELD);
                }

                $user = $result[0][LDAP_USERNAME_FIELD][0];
                if (defined('LDAP_BIND_PREFIX')) {
                    $user = LDAP_BIND_PREFIX . $user;
                }
                if (defined('LDAP_BIND_SUFFIX')) {
                    $user .= LDAP_BIND_SUFFIX;
                }

                if (!isset($result[0][LDAP_EMAIL_FIELD])) {
                    if (!isset($result[0][strtolower(LDAP_EMAIL_FIELD)])) {
                        die("Use all lower case LDAP_EMAIL_FIELD!");
                    }
                    die("found ldap account object does not contain the mail field: ". LDAP_EMAIL_FIELD);
                }

                echo "Trying to authenticate as user: " . $user . PHP_EOL;
                $bindResult = ldap_bind($ds, $user, $password);
                if (false !== $bindResult) {
                    echo "authentication success" . PHP_EOL;
                    foreach ($result[0][LDAP_EMAIL_FIELD] as $email) {
                        if (0 === strpos($email, 'SMTP')) {
                            $email = strtolower(substr($email, 5));
                            echo "found mail: " . $email . PHP_EOL;
                        }
                    }

                    if (!isset($email)) {
                        die("no smtp mail found");
                    }
                    echo "db data for account: Mail: " . $email . "; Internal account id" . $result[0]['cn'][0] . PHP_EOL;
                    die("login success".PHP_EOL);
                }

                if (ldap_errno($ds) === 49) {
                    //LDAP_INVALID_CREDENTIALS
                    die("invalid credentials");
                }
                die(ldap_print_error($ds));
            }
        }
