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

class Format
{
    /**
     * @param $number
     * @return string
     */
    public static function suppress_zeros($number)
    {
        if (abs($number - 0.0) < 0.1) {
            return '.';
        }

        return $number;
    }

    /**
     * @param $string
     * @return false|int
     */
    public static function is_utf8($string)
    {
        // From http://w3.org/International/questions/qa-forms-utf-8.html
        return preg_match('%^(?:
          [\x09\x0A\x0D\x20-\x7E]            # ASCII
        | [\xC2-\xDF][\x80-\xBF]             # non-overlong 2-byte
        |  \xE0[\xA0-\xBF][\x80-\xBF]        # excluding overlongs
        | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}  # straight 3-byte
        |  \xED[\x80-\x9F][\x80-\xBF]        # excluding surrogates
        |  \xF0[\x90-\xBF][\x80-\xBF]{2}     # planes 1-3
        | [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15
        |  \xF4[\x80-\x8F][\x80-\xBF]{2}     # plane 16
    )*$%xs', $string);
    }

    /**
     * @param $string
     * @return string
     */
    public static function getUTF8String($string)
    {
        if (function_exists('mb_check_encoding')) {
            if (!mb_check_encoding($string, 'UTF-8')) {
                $string = mb_convert_encoding($string, 'UTF-8');
            }
        } else {
            if (!self::is_utf8($string)) {
                $string = utf8_encode($string);
            }
        }

        return $string;
    }

    /**
     * @param double $size
     * @param int $precision
     * @return string
     */
    public static function formatSize($size, $precision = 2)
    {
        if ($size === null) {
            return 'n/a';
        }
        if ($size === 0 || $size === '0') {
            return '0';
        }
        $base = log($size) / log(1024);
        $suffixes = ['B', 'kB', 'MB', 'GB', 'TB', 'PB'];

        return round(1024 ** ($base - floor($base)), $precision) . $suffixes[(int)floor($base)];
    }

    /**
     * @param $data_in
     * @param $info_out
     */
    public static function format_report_volume(&$data_in, &$info_out)
    {
        // Measures
        $kb = 1024;
        $mb = 1024 * $kb;
        $gb = 1024 * $mb;
        $tb = 1024 * $gb;

        // Copy the data to a temporary variable
        $temp = $data_in;

        // Work out the average size of values in the array
        $count = count($temp);
        $sum = array_sum($temp);
        $average = $sum / $count;

        // Work out the largest value in the array
        arsort($temp);
        array_pop($temp);

        // Calculate the correct display size for the average value
        if ($average < $kb) {
            $info_out['formula'] = 1;
            $info_out['shortdesc'] = 'b';
            $info_out['longdesc'] = 'Bytes';
        } else {
            if ($average < $mb) {
                $info_out['formula'] = $kb;
                $info_out['shortdesc'] = 'Kb';
                $info_out['longdesc'] = 'Kilobytes';
            } else {
                if ($average < $gb) {
                    $info_out['formula'] = $mb;
                    $info_out['shortdesc'] = 'Mb';
                    $info_out['longdesc'] = 'Megabytes';
                } else {
                    if ($average < $tb) {
                        $info_out['formula'] = $gb;
                        $info_out['shortdesc'] = 'Gb';
                        $info_out['longdesc'] = 'Gigabytes';
                    } else {
                        $info_out['formula'] = $tb;
                        $info_out['shortdesc'] = 'Tb';
                        $info_out['longdesc'] = 'Terabytes';
                    }
                }
            }
        }

        // Modify the original data accordingly
        $num_data_in = count($data_in);
        for ($i = 0; $i < $num_data_in; $i++) {
            $data_in[$i] /= $info_out['formula'];
        }
    }

    /**
     * @param $input
     * @param $maxlen
     * @return string
     */
    public static function trim_output($input, $maxlen)
    {
        if ($maxlen > 0 && strlen($input) >= $maxlen) {
            return substr($input, 0, $maxlen) . '...';
        }

        return $input;
    }

    /**
     * @param $date
     * @param string $format
     * @return mixed|string
     */
    public static function translateQuarantineDate($date, $format = 'dmy')
    {
        $y = substr($date, 0, 4);
        $m = substr($date, 4, 2);
        $d = substr($date, 6, 2);

        $format = strtolower($format);

        switch ($format) {
            case 'dmy':
                return "$d/$m/$y";
            case 'sql':
                return "$y-$m-$d";
            default:
                $format = preg_replace('/%y/', $y, $format);
                $format = preg_replace('/%m/', $m, $format);
                $format = preg_replace('/%d/', $d, $format);

                return $format;
        }
    }

    /**
     * @param $ip
     * @return string
     */
    public function stripPortFromIp($ip)
    {
        if (preg_match('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\:\d{1,5}/', $ip)) {
            $ip = current(array_slice(explode(':', $ip), 0, 1));
        }

        return $ip;
    }
}
