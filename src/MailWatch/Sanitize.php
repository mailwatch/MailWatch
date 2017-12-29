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

class Sanitize
{
    /**
     * @param $string
     * @return mixed
     */
    public static function sanitizeInput($string)
    {
        $config = \HTMLPurifier_Config::createDefault();
        $purifier = new \HTMLPurifier($config);

        return $purifier->purify($string);
    }

    /**
     * @param $value
     * @return string
     */
    public static function quote_smart($value)
    {
        return "'" . Sanitize::safe_value($value) . "'";
    }

    /**
     * @param $value
     * @return string
     */
    public static function safe_value($value)
    {
        $link = Db::connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) {
            $value = stripslashes($value);
        }
        $value = $link->real_escape_string($value);

        return $value;
    }

    /**
     * @param $input
     * @param $type
     * @return bool|mixed|string
     */
    public static function deepSanitizeInput($input, $type)
    {
        switch ($type) {
            case 'email':
                $string = filter_var($input, FILTER_SANITIZE_EMAIL);
                $string = Sanitize::sanitizeInput($string);
                $string = Sanitize::safe_value($string);

                return $string;
            case 'url':
                $string = filter_var($input, FILTER_SANITIZE_URL);
                $string = Sanitize::sanitizeInput($string);
                $string = htmlentities($string);
                $string = Sanitize::safe_value($string);

                return $string;
            case 'num':
                $string = filter_var($input, FILTER_SANITIZE_NUMBER_INT);
                $string = Sanitize::sanitizeInput($string);
                $string = Sanitize::safe_value($string);

                return $string;
            case 'float':
                $string = filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                $string = Sanitize::sanitizeInput($string);
                $string = Sanitize::safe_value($string);

                return $string;
            case 'string':
                $string = filter_var($input, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_BACKTICK);
                $string = Sanitize::sanitizeInput($string);
                $string = Sanitize::safe_value($string);

                return $string;
            default:
                return false;
        }
    }

    /**
     * @param $input
     * @param $type
     * @return bool
     */
    public static function validateInput($input, $type)
    {
        switch ($type) {
            case 'email':
                if (filter_var($input, FILTER_VALIDATE_EMAIL)) {
                    return true;
                }
                break;
            case 'user':
                if (preg_match('/^[\p{L}\p{M}\p{N}\&~!@$%^*=_:.\/+-]{1,256}$/u', $input)) {
                    return true;
                }
                break;
            case 'general':
                if (preg_match('/^[\p{L}\p{M}\p{N}\p{Z}\p{P}\p{S}]{1,256}$/u', $input)) {
                    return true;
                }
                break;
            case 'yn':
                if (preg_match('/^[YNyn]$/', $input)) {
                    return true;
                }
                break;
            case 'quardir':
                if (preg_match('/^[0-9]{8}$/', $input)) {
                    return true;
                }
                break;
            case 'num':
                if (preg_match('/^[0-9]{1,256}$/', $input)) {
                    return true;
                }
                break;
            case 'float':
                if (is_float(filter_var($input, FILTER_VALIDATE_FLOAT))) {
                    return true;
                }
                break;
            case 'orderby':
                if (preg_match('/^(datetime|from_address|to_address|subject|size|sascore)$/', $input)) {
                    return true;
                }
                break;
            case 'orderdir':
                if (preg_match('/^[ad]$/', $input)) {
                    return true;
                }
                break;
            case 'msgid':
                if (preg_match(
                    '/^([A-F0-9]{7,20}\.[A-F0-9]{5}|[0-9B-DF-HJ-NP-TV-Zb-df-hj-np-tv-z.]{8,16}(?=z[A-Za-x]{4,8})|[0-9A-Za-z]{6}-[A-Za-z0-9]{6}-[A-Za-z0-9]{2}|[0-9A-Za-z]{12,14})$/',
                    $input
                )) {
                    return true;
                }
                break;
            case 'urltype':
                if (preg_match('/^[hf]$/', $input)) {
                    return true;
                }
                break;
            case 'host':
                if (preg_match('/^[\p{N}\p{L}\p{M}.:-]{2,256}$/u', $input)) {
                    return true;
                }
                break;
            case 'list':
                if (preg_match('/^[wb]$/', $input)) {
                    return true;
                }
                break;
            case 'listsubmit':
                if (preg_match('/^(add|delete)$/', $input)) {
                    return true;
                }
                break;
            case 'releasetoken':
                if (preg_match('/^[0-9A-Fa-f]{20}$/', $input)) {
                    return true;
                }
                break;
            case 'resetid':
                if (preg_match('/^[0-9A-Za-z]{32}$/', $input)) {
                    return true;
                }
                break;
            case 'mailq':
                if (preg_match('/^(inq|outq)$/', $input)) {
                    return true;
                }
                break;
            case 'salearnops':
                if (preg_match('/^(spam|ham|forget|report|revoke)$/', $input)) {
                    return true;
                }
                break;
            case 'file':
                if (preg_match('/^[A-Za-z0-9._-]{2,256}$/', $input)) {
                    return true;
                }
                break;
            case 'date':
                if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $input)) {
                    return true;
                }
                break;
            case 'alnum':
                if (preg_match('/^[0-9A-Za-z]{1,256}$/', $input)) {
                    return true;
                }
                break;
            case 'ip':
                if (filter_var($input, FILTER_VALIDATE_IP)) {
                    return true;
                }
                break;
            case 'action':
                if (preg_match('/^(new|edit|delete|filters|logout)$/', $input)) {
                    return true;
                }
                break;
            case 'type':
                if (preg_match('/^[UDA]$/', $input)) {
                    return true;
                }
                break;
            case 'mimepart':
                if (preg_match('/^[0-9.]{1,10}$/', $input)) {
                    return true;
                }
                break;
            case 'loginerror':
                if (preg_match('/^(baduser|emptypassword|timeout)$/', $input)) {
                    return true;
                }
                break;
            case 'timeout':
                if (preg_match('/^[0-9]{1,5}$/', $input)) {
                    return true;
                }
                break;
            default:
                return false;
        }
        return false;
    }
}
