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
