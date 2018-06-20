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

class Translation
{
    public static $langCode;
    private static $lang;
    private static $systemLang;

    /**
     * @param string $string
     * @param bool $useSystemLang
     * @return string
     */
    public static function __($string, $useSystemLang = false)
    {
        if ($useSystemLang) {
            $language = self::$systemLang;
        } else {
            $language = self::$lang;
        }

        $debug_message = '';
        $pre_string = '';
        $post_string = '';
        if (DEBUG === true) {
            $debug_message = ' (' . $string . ')';
            $pre_string = '<span class="error">';
            $post_string = '</span>';
        }
        if (isset($language[$string])) {
            return $language[$string] . $debug_message;
        }

        $en_lang = \MailWatch\Languages\en::$TRANSLATION;
        if (isset($en_lang[$string])) {
            return $pre_string . $en_lang[$string] . $debug_message . $post_string;
        }

        return $pre_string . $language['i18_missing'] . $debug_message . $post_string;
    }

    /**
     *  for compatibility with old code
     */
    public static function configureLanguage()
    {
        $session_cookie_secure = false;
        if (SSL_ONLY === true) {
            ini_set('session.cookie_secure', 1);
            $session_cookie_secure = true;
        }

        if (!defined('LANG')) {
            define('LANG', 'en');
        }
        self::$langCode = LANG;
        // If the user is allowed to select the language for the gui check which language he has choosen or create the cookie with the default lang
        if (defined('USER_SELECTABLE_LANG')) {
            if (isset($_COOKIE['MW_LANG']) && self::checkLangCode($_COOKIE['MW_LANG'])) {
                self::$langCode = $_COOKIE['MW_LANG'];
            } else {
                setcookie('MW_LANG', LANG, 0, (session_get_cookie_params())['path'], (session_get_cookie_params())['domain'], $session_cookie_secure, false);
            }
        }

        // Load the lang file or en if the spicified language is not available
        $langClass = '\\MailWatch\\Languages\\' . self::$langCode;
        if (!class_exists($langClass)) {
            self::$lang = \MailWatch\Languages\en::$TRANSLATION;
        } else {
            self::$lang = (new $langClass)::$TRANSLATION;
        }

        // Load the lang file or en if the spicified language is not available
        $sysLangClass = '\\MailWatch\\Languages\\' . LANG;
        if (!class_exists($sysLangClass)) {
            self::$systemLang = \MailWatch\Languages\en::$TRANSLATION;
        } else {
            self::$systemLang = (new $sysLangClass)::$TRANSLATION;
        }
    }

    /**
     * Checks if the passed language code is allowed to be used for the users
     * @param string $langCode
     * @return bool
     */
    public static function checkLangCode($langCode)
    {
        $validLang = explode(',', USER_SELECTABLE_LANG);
        $found = array_search($langCode, $validLang);
        if ($found === false || $found === null) {
            \MailWatch\Security::audit_log(sprintf(\MailWatch\Translation::__('auditundefinedlang12', true), $langCode));

            return false;
        }

        return true;
    }
}
