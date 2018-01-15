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


class Debug
{
    /**
     * @param $text
     */
    public static function debug($text)
    {
        if (true === DEBUG && headers_sent()) {
            echo "<!-- DEBUG: $text -->\n";
        }
    }

    /**
     * @param $link
     * @param $sql
     */
    public static function dbquerydebug($link, $sql)
    {
        echo "<!--\n\n";
        $dbg_sql = 'EXPLAIN ' . $sql;
        echo "SQL:\n\n$sql\n\n";
        /** @var mysqli_result $result */
        $result = $link->query($dbg_sql);
        if ($result) {
            while ($row = $result->fetch_row()) {
                for ($f = 0; $f < $link->field_count; $f++) {
                    echo $result->fetch_field_direct($f)->name . ': ' . $row[$f] . "\n";
                }
            }

            echo "\n-->\n\n";
            $result->free_result();
        } else {
            die(__('diedbquery03') . '(' . $link->connect_errno . ' ' . $link->connect_error . ')');
        }
    }

    /**
     * @param $input
     * @return string
     */
    public static function debug_print_r($input)
    {
        ob_start();
        print_r($input);
        $return = ob_get_contents();
        ob_end_clean();

        return $return;
    }
}