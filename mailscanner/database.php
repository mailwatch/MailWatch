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

class database
{
    /** @var mysqli $link */
    public static $link;

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
        if (!self::$link instanceof mysqli) {
            try {
                $driver = new mysqli_driver();
                $driver->report_mode = MYSQLI_REPORT_ALL;
                set_error_handler(function () {
                });
                self::$link = new mysqli($host, $username, $password, $database);
                restore_error_handler();
                self::$link->options(MYSQLI_INIT_COMMAND, "SET sql_mode=(SELECT TRIM(BOTH ',' FROM REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY','')))");
                $charset = 'utf8';
                $collation = 'utf8_unicode_ci';
                if (self::$link->server_version >= 50503) {
                    //mysql version supports utf8mb4
                    $charset = 'utf8mb4';
                    $collation = 'utf8mb4_unicode_ci';
                }
                if (false === self::$link->set_charset($charset)) {
                    self::$link->query('SET NAMES ' . $charset . ' COLLATE ' . $collation);
                }
            } catch (Exception $e) {
                if (PHP_SAPI !== 'cli') {
                    $output = '
<style>
.db-error {
    width: 40%;
    margin: 0 auto;
    text-align: center;
    margin-top: 100px;
    border: solid 3px #ebcccc;
    -webkit-border-radius:20px;
    -moz-border-radius:20px;
    border-radius:20px;
    background-color: #f2dede;
    color: #a94442;
}

.db-error .emphasise {
    font-weight:bold;
    font-size:larger;
}
</style>
                <div class="db-error">';
                    $output .= __('dbconnecterror99');
                    $output .= '</div>';
                } else {
                    $output = __('dbconnecterror99_plain') . PHP_EOL;
                }
                die($output);
            }
        }
        return self::$link;
    }

    /**
     * @return bool
     */
    public static function close()
    {
        $result = true;
        if (self::$link instanceof mysqli) {
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
