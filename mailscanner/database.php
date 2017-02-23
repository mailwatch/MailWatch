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
 * As a special exception, you have permission to link this program with the JpGraph library and distribute executables,
 * as long as you follow the requirements of the GNU GPL in regard to all of the software in the executable aside from
 * JpGraph.
 *
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free
 * Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

class database
{
    /** @var mysqli $link */
    public static $link = null;

    private function __construct()
    {
    }

    /**
     * @param string $host
     * @param string $username
     * @param string $password
     * @param string $database
     * @return mysqli
     */
    public static function connect($host = '', $username = '', $password = '', $database = '')
    {
        if (self::$link == null || !self::$link) {
            self::$link = new mysqli($host, $username, $password, $database);
            if (self::$link->connect_error) {
                die(__('diedbconn103') . '(' . self::$link->connect_errno . ' ' . self::$link->connect_error . ')');
            }
            $charset = 'utf8';
            if (self::$link->server_version >= 50503) {
                //mysql version supports utf8mb4
                $charset = 'utf8mb4';
            }
            self::$link->set_charset($charset);
        }
        return self::$link;
    }

    /**
     * @return bool
     */
    public static function close()
    {
        $result = true;
        if (self::$link != null) {
            $result = self::$link->close();
            self::$link = null;
        }
        return $result;
    }

    /**
     * @param mysqli_result $result
     * @param int $row
     * @param int|string $col
     * @return bool|mixed
     */
    public static function mysqli_result(mysqli_result $result, $row = 0, $col = 0)
    {
        $numrows = $result->num_rows;
        if ($numrows && $row <= ($numrows - 1) && $row >= 0) {
            mysqli_data_seek($result, $row);
            $resrow = is_numeric($col) ? mysqli_fetch_row($result) : mysqli_fetch_assoc($result);
            if (isset($resrow[$col])) {
                return $resrow[$col];
            }
        }
        return false;
    }
}
