<?php

/*
 * MailWatch for MailScanner
 * Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 * Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 * Copyright (C) 2014-2017  MailWatch Team (https://github.com/mailwatch/1.2.0/graphs/contributors)
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
     * @param $action
     * @return bool
     */
    public static function audit_log($action)
    {
        $link = \MailWatch\Db::connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if (AUDIT) {
            $user = 'unknown';
            if (isset($_SESSION['myusername'])) {
                $user = $link->real_escape_string($_SESSION['myusername']);
            }

            $action =  \MailWatch\Sanitize::safe_value($action);
            $ip =  \MailWatch\Sanitize::safe_value($_SERVER['REMOTE_ADDR']);
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
    function updateUserPasswordHash($user, $hash)
    {
        $sqlCheckLenght = "SELECT CHARACTER_MAXIMUM_LENGTH AS passwordfieldlength FROM information_schema.columns WHERE column_name = 'password' AND table_name = 'users'";
        $passwordFiledLengthResult = Db::query($sqlCheckLenght);
        $passwordFiledLength = (int)Db::mysqli_result($passwordFiledLengthResult, 0, 'passwordfieldlength');

        if ($passwordFiledLength < 255) {
            $sqlUpdateFieldLength = 'ALTER TABLE `users` CHANGE `password` `password` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL';
            Db::query($sqlUpdateFieldLength);
            Security::audit_log(sprintf(__('auditlogquareleased03', true) . ' ', $passwordFiledLength));
        }

        $sqlUpdateHash = "UPDATE `users` SET `password` = '$hash' WHERE `users`.`username` = '$user'";
        Db::query($sqlUpdateHash);
        Security::audit_log(__('auditlogupdateuser03', true) . ' ' . $user);
    }
}