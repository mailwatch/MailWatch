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

namespace MailWatch;

class Ldap
{
    /**
     * @param $username
     * @param $password
     * @return null|string
     */
    public static function authenticate($username, $password)
    {
        $username = ldap_escape(strtolower($username), '', LDAP_ESCAPE_DN);
        if ($username !== '' && $password !== '') {
            $ds = ldap_connect(LDAP_HOST, LDAP_PORT) or die(Translation::__('ldpaauth103') . ' ' . LDAP_HOST);

            $ldap_protocol_version = 3;
            if (defined('LDAP_PROTOCOL_VERSION')) {
                $ldap_protocol_version = LDAP_PROTOCOL_VERSION;
            }
            // Check if Microsoft Active Directory compatibility is enabled
            if (defined('LDAP_MS_AD_COMPATIBILITY') && LDAP_MS_AD_COMPATIBILITY === true) {
                ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);
                $ldap_protocol_version = 3;
            }
            ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, $ldap_protocol_version);

            $bindResult = @ldap_bind($ds, LDAP_USER, LDAP_PASS);
            if (false === $bindResult) {
                die(self::print_error($ds));
            }

            //search for $user in LDAP directory
            $ldap_search_results = ldap_search($ds, LDAP_DN, sprintf(LDAP_FILTER, $username)) or die(Translation::__('ldpaauth203'));

            if (false === $ldap_search_results) {
                @trigger_error(Translation::__('ldapnoresult03') . ' "' . $username . '"');

                return null;
            }
            if (1 > ldap_count_entries($ds, $ldap_search_results)) {
                @trigger_error(Translation::__('ldapresultnodata03') . ' "' . $username . '"');

                return null;
            }
            if (ldap_count_entries($ds, $ldap_search_results) > 1) {
                @trigger_error(Translation::__('ldapresultset03') . ' "' . $username . '" ' . Translation::__('ldapisunique03'));

                return null;
            }

            if ($ldap_search_results) {
                $result = ldap_get_entries($ds, $ldap_search_results) or die(Translation::__('ldpaauth303'));
                ldap_free_result($ldap_search_results);
                if (isset($result[0])) {
                    if (in_array('group', array_values($result[0]['objectclass']), true)) {
                        // do not login as group
                        return null;
                    }

                    if (!isset($result[0][LDAP_USERNAME_FIELD][0])) {
                        @trigger_error(Translation::__('ldapno03') . ' "' . LDAP_USERNAME_FIELD . '" ' . Translation::__('ldapresults03'));

                        return null;
                    }

                    $user = $result[0][LDAP_USERNAME_FIELD][0];
                    if (defined('LDAP_BIND_PREFIX')) {
                        $user = LDAP_BIND_PREFIX . $user;
                    }
                    if (defined('LDAP_BIND_SUFFIX')) {
                        $user .= LDAP_BIND_SUFFIX;
                    }

                    if (!isset($result[0][LDAP_EMAIL_FIELD])) {
                        @trigger_error(Translation::__('ldapno03') . ' "' . LDAP_EMAIL_FIELD . '" ' . Translation::__('ldapresults03'));

                        return null;
                    }

                    $bindResult = @ldap_bind($ds, $user, $password);
                    if (false !== $bindResult) {
                        foreach ($result[0][LDAP_EMAIL_FIELD] as $email) {
                            if (0 === strpos($email, 'SMTP')) {
                                $email = strtolower(substr($email, 5));
                                break;
                            }
                        }

                        if (!isset($email)) {
                            //user has no mail but it is required for mailwatch
                            return null;
                        }

                        $sql = sprintf('SELECT username FROM users WHERE username = %s', Sanitize::quote_smart($email));
                        $sth = Db::query($sql);
                        if ($sth->num_rows === 0) {
                            $sql = sprintf(
                                "REPLACE INTO users (username, fullname, type, password) VALUES (%s, %s,'U',NULL)",
                                Sanitize::quote_smart($email),
                                Sanitize::quote_smart($result[0]['cn'][0])
                            );
                            Db::query($sql);
                        }

                        return $email;
                    }

                    if (ldap_errno($ds) === 49) {
                        //LDAP_INVALID_CREDENTIALS
                        return null;
                    }
                    die(self::print_error($ds));
                }
            }
        }

        return null;
    }

    /**
     * @param $ds
     * @return string
     */
    public static function print_error($ds)
    {
        return sprintf(
            Translation::__('ldapnobind03'),
            LDAP_HOST,
            ldap_errno($ds),
            ldap_error($ds)
        );
    }

    /**
     * This function appears to be unused - can probably be removed
     * @param $entry
     * @return string
     */
    public static function ldap_get_conf_var($entry)
    {
        // Translate MailScanner.conf vars to internal
        $entry = translate_etoi($entry);

        $lh = ldap_connect(LDAP_HOST, LDAP_PORT)
        or die(Translation::__('ldapgetconfvar103') . ' ' . LDAP_HOST . "\n");

        @ldap_bind($lh)
        or die(Translation::__('ldapgetconfvar203') . "\n");

        // As per MailScanner Config.pm
        $filter = '(objectClass=mailscannerconfmain)';
        $filter = "(&$filter(mailScannerConfBranch=main))";

        $sh = ldap_search($lh, LDAP_DN, $filter, [$entry]);

        $info = ldap_get_entries($lh, $sh);
        if ($info['count'] > 0 && $info[0]['count'] !== 0) {
            if ($info[0]['count'] === 0) {
                // Return single value
                return $info[0][$info[0][0]][0];
            }

            // Multi-value option, build array and return as space delimited
            $return = [];
            for ($n = 0; $n < $info[0][$info[0][0]]['count']; $n++) {
                $return[] = $info[0][$info[0][0]][$n];
            }

            return implode(' ', $return);
        }

        // No results
        die(Translation::__('ldapgetconfvar303') . " '$entry' " . Translation::__('ldapgetconfvar403') . "\n");
    }

    /**
     * This function appears to be unused - can probably be removed
     * @param $entry
     * @return bool
     */
    public static function ldap_get_conf_truefalse($entry)
    {
        // Translate MailScanner.conf vars to internal
        $entry = translate_etoi($entry);

        $lh = ldap_connect(LDAP_HOST, LDAP_PORT)
        or die(Translation::__('ldapgetconfvar103') . ' ' . LDAP_HOST . "\n");

        @ldap_bind($lh)
        or die(Translation::__('ldapgetconfvar203') . "\n");

        // As per MailScanner Config.pm
        $filter = '(objectClass=mailscannerconfmain)';
        $filter = "(&$filter(mailScannerConfBranch=main))";

        $sh = ldap_search($lh, LDAP_DN, $filter, [$entry]);

        $info = ldap_get_entries($lh, $sh);
        Debug::debug(Debug::debug_print_r($info));
        if ($info['count'] > 0) {
            Debug::debug('Entry: ' . Debug::debug_print_r($info[0][$info[0][0]][0]));
            switch ($info[0][$info[0][0]][0]) {
                case 'yes':
                case '1':
                    return true;
                case 'no':
                case '0':
                default:
                    return false;
            }
        } else {
            // No results
            //die(\MailWatch\Translation::__('ldapgetconfvar303') . " '$entry' " . \MailWatch\Translation::__('ldapgetconfvar403') . "\n");
            return false;
        }
    }
}
