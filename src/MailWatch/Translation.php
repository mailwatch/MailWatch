<?php

namespace MailWatch;

class Translation
{
    /**
     * @param string $string
     * @param boolean $useSystemLang
     * @return string
     */
    public static function __($string, $useSystemLang = false)
    {
        if ($useSystemLang) {
            global $systemLang;
            $language = $systemLang;
        } else {
            global $lang;
            $language = $lang;
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

        $en_lang = require __DIR__ . DIRECTORY_SEPARATOR . 'languages' . DIRECTORY_SEPARATOR . 'en.php';
        if (isset($en_lang[$string])) {
            return $pre_string . $en_lang[$string] . $debug_message . $post_string;
        }

        return $pre_string . $language['i18_missing'] . $debug_message . $post_string;
    }
}
