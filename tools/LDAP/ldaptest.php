<?php
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

                if (!isset($result[0][LDAP_USERNAME_FIELD], $result[0][LDAP_USERNAME_FIELD][0])) {
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
