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

class Security
{
    public static function disableBrowserCache()
    {
        header('Expires: Sat, 10 May 2003 00:00:00 GMT');
        header('Last-Modified: ' . gmdate('D, M d Y H:i:s') . ' GMT');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Cache-Control: post-check=0, pre-check=0', false);
    }

    /**
     * @param string $action
     * @param string $user
     * @return bool
     */
    public static function audit_log($action, $user = 'unknown')
    {
        $link = \MailWatch\Db::connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if (AUDIT) {
            if (isset($_SESSION['myusername'])) {
                $user = $link->real_escape_string($_SESSION['myusername']);
            }

            $action = \MailWatch\Sanitize::safe_value($action);
            $ip = null;
            if (isset($_SERVER['REMOTE_ADDR'])) {
                $ip = \MailWatch\Sanitize::safe_value($_SERVER['REMOTE_ADDR']);
            }
            $ret = \MailWatch\Db::query("INSERT INTO audit_log (user, ip_address, action) VALUES ('$user', '$ip', '$action')");
            if ($ret) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $user
     * @param $hash
     */
    public static function updateUserPasswordHash($user, $hash)
    {
        $sqlCheckLenght = "SELECT CHARACTER_MAXIMUM_LENGTH AS passwordfieldlength FROM information_schema.columns WHERE column_name = 'password' AND table_name = 'users'";
        $passwordFiledLengthResult = Db::query($sqlCheckLenght);
        $passwordFiledLength = (int)Db::mysqli_result($passwordFiledLengthResult, 0, 'passwordfieldlength');

        if ($passwordFiledLength < 255) {
            $sqlUpdateFieldLength = 'ALTER TABLE `users` CHANGE `password` `password` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL';
            Db::query($sqlUpdateFieldLength);
            self::audit_log(sprintf(\MailWatch\Translation::__('auditlogquareleased03', true) . ' ', $passwordFiledLength));
        }

        $sqlUpdateHash = "UPDATE `users` SET `password` = '$hash' WHERE `users`.`username` = '$user'";
        Db::query($sqlUpdateHash);
        self::audit_log(\MailWatch\Translation::__('auditlogupdateuser03', true) . ' ' . $user);
    }

    /**
     * @param integer $length
     * @return bool|string
     * @throws \Exception
     */
    public static function get_random_string($length)
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * @return bool|string
     * @throws \Exception
     */
    public static function generateToken()
    {
        $tokenLenght = 32;

        return self::get_random_string($tokenLenght);
    }

    /**
     * @param $token
     * @return bool
     */
    public static function checkToken($token)
    {
        if (!isset($_SESSION['token'])) {
            return false;
        }

        return $_SESSION['token'] === Sanitize::deepSanitizeInput($token, 'url');
    }

    /**
     * @param string $formstring
     * @return string
     */
    public static function generateFormToken($formstring)
    {
        if (!isset($_SESSION['token'])) {
            die(\MailWatch\Translation::__('dietoken99'));
        }

        $calc = hash_hmac('sha256', $formstring . $_SESSION['token'], $_SESSION['formtoken']);

        return $calc;
    }

    /**
     * @param string $formstring
     * @param string $formtoken
     * @return bool
     */
    public static function checkFormToken($formstring, $formtoken)
    {
        if (!isset($_SESSION['token'], $_SESSION['formtoken'])) {
            return false;
        }
        $calc = hash_hmac('sha256', $formstring . $_SESSION['token'], $_SESSION['formtoken']);

        return $calc === Sanitize::deepSanitizeInput($formtoken, 'url');
    }

    /**
     * Updates the user login expiry
     * @param string $myusername
     * @return bool|\mysqli_result
     */
    public static function updateLoginExpiry($myusername)
    {
        $sql = "SELECT login_timeout FROM users WHERE username='" . Sanitize::safe_value($myusername) . "'";
        $result = Db::query($sql);

        if ($result->num_rows === 0) {
            // Something went wrong, or user no longer exists
            return false;
        }

        $login_timeout = Db::mysqli_result($result, 0, 'login_timeout');

        // Use global if individual value is disabled (-1)
        if ($login_timeout === '-1') {
            if (defined('SESSION_TIMEOUT')) {
                if (SESSION_TIMEOUT > 0 && SESSION_TIMEOUT <= 99999) {
                    $expiry_val = (time() + SESSION_TIMEOUT);
                } else {
                    $expiry_val = 0;
                }
            } else {
                $expiry_val = (time() + 600);
            }
            // If set, use the individual timeout
        } elseif ($login_timeout === '0') {
            $expiry_val = 0;
        } else {
            $expiry_val = (time() + (int)$login_timeout);
        }
        $sql = "UPDATE users SET login_expiry='" . $expiry_val . "', last_login='" . time() . "' WHERE username='" . Sanitize::safe_value($myusername) . "'";
        $result = Db::query($sql);

        return $result;
    }

    /**
     * Checks the user login expiry against the current time, if enabled
     * Returns true if expired
     * @param string $myusername
     * @return bool
     */
    public static function checkLoginExpiry($myusername)
    {
        $sql = "SELECT login_expiry FROM users WHERE username='" . Sanitize::safe_value($myusername) . "'";
        $result = Db::query($sql);

        if ($result->num_rows === 0) {
            // Something went wrong, or user no longer exists
            return true;
        }

        $login_expiry = Db::mysqli_result($result, 0, 'login_expiry');

        if ($login_expiry === '-1') {
            // User administratively logged out
            return true;
        }

        if ($login_expiry === '0') {
            // Login never expires, so just return false
            return false;
        }

        if ((int)$login_expiry > time()) {
            // User is active
            return false;
        }

        // User has timed out
        return true;
    }

    /**
     * Checks for a privilege change, returns true if changed
     * @param string $myusername
     * @return bool
     */
    public static function checkPrivilegeChange($myusername)
    {
        $sql = "SELECT type FROM users WHERE username='" . Sanitize::safe_value($myusername) . "'";
        $result = Db::query($sql);

        if ($result->num_rows === 0) {
            // Something went wrong, or user does not exist
            return true;
        }

        $user_type = Db::mysqli_result($result, 0, 'type');

        return $_SESSION['user_type'] !== $user_type;
    }
}
