<?php
/**
 * Created by PhpStorm.
 * User: Alan Urquhart
 * Company: ASU Web Services LTD
 * Web: www.asuweb.co.uk
 * Date: 08/01/2018
 * Time: 13:53
 */

namespace MailWatch\MTA;


class Sendmail
{
    /**
     * @param $header
     * @return mixed|string
     */
    public static function getFROMheader($header)
    {
        $sender = '';
        if (preg_match('/From:([ ]|\n)(.*(?=((\d{3}[A-Z]?[ ]+(\w|[-])+:.*)|(\s*\z))))/sUi', $header, $match) === 1) {
            if (isset($match[2])) {
                $sender = $match[2];
            }
            if (preg_match('/\S+@\S+/', $sender, $match_email) === 1 && isset($match_email[0])) {
                $sender = str_replace(['<', '>', '"'], '', $match_email[0]);
            }
        }
        return $sender;
    }

    /**
     * @param $header
     * @return string
     */
    public static function getSUBJECTheader($header)
    {
        $subject = '';
        if (preg_match('/^\d{3}  Subject:([ ]|\n)(.*(?=((\d{3}[A-Z]?[ ]+(\w|[-])+:.*)|(\s*\z))))/iUsm', $header, $match) === 1) {
            $subLines = preg_split('/[\r\n]+/', $match[2]);
            for ($i = 0, $countSubLines = count($subLines); $i < $countSubLines; $i++) {
                $convLine = '';
                if (function_exists('imap_mime_header_decode')) {
                    $linePartArr = imap_mime_header_decode($subLines[$i]);
                    for ($j = 0, $countLinePartArr = count($linePartArr); $j < $countLinePartArr; $j++) {
                        if (strtolower($linePartArr[$j]->charset) === 'default') {
                            if ($linePartArr[$j]->text !== ' ') {
                                $convLine .= $linePartArr[$j]->text;
                            }
                        } else {
                            $textdecoded = @iconv(
                                strtoupper($linePartArr[$j]->charset),
                                'UTF-8//TRANSLIT//IGNORE',
                                $linePartArr[$j]->text
                            );
                            if (!$textdecoded) {
                                $convLine .= $linePartArr[$j]->text;
                            } else {
                                $convLine .= $textdecoded;
                            }
                        }
                    }
                } else {
                    $convLine .= str_replace('_', ' ', mb_decode_mimeheader($subLines[$i]));
                }
                $subject .= $convLine;
            }
        }

        return $subject;
    }
}