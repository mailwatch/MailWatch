<?php

/*
 * MailWatch for MailScanner
 * Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 * Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 * Copyright (C) 2014-2021  MailWatch Team (https://github.com/mailwatch/1.2.0/graphs/contributors)
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

class Database
{
    public static ?mysqli $link = null;

    private function __construct()
    {
    }

    public static function connect(
        string $host = '',
        string $username = '',
        string $password = '',
        string $database = '',
        int $port = 3306
    ): mysqli {
        if (!self::$link instanceof mysqli) {
            $driver = new mysqli_driver();
            $driver->report_mode = MYSQLI_REPORT_ALL;
            set_error_handler(static function(int $errno, string $errstr, string $errfile, int $errline): bool {
                return false;
            });
            self::$link = new mysqli($host, $username, $password, $database, $port);
            restore_error_handler();
            self::$link->options(MYSQLI_INIT_COMMAND, "SET sql_mode=(SELECT TRIM(BOTH ',' FROM REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY','')))");

            // mysql version 5.7+ supports utf8mb4
            $charset = 'utf8mb4';
            $collation = 'utf8mb4_unicode_520_ci';

            if (false === self::$link->set_charset($charset)) {
                self::$link->query('SET NAMES ' . $charset . ' COLLATE ' . $collation);
            }

            // Stored points in time are UTC. Pinning the session keeps NOW() and
            // any TIMESTAMP column on the same clock as the data, whatever zone
            // the database server happens to be set to.
            self::$link->query("SET time_zone = '+00:00'");
        }

        return self::$link;
    }

    public static function close(): bool
    {
        $result = true;
        if (self::$link instanceof mysqli) {
            $result = self::$link->close();
            self::$link = null;
        }

        return $result;
    }

    public static function mysqli_result(
        mysqli_result $result,
        int $row = 0,
        int|string $col = 0
    ): mixed {
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

    public static function getDatabaseVersion(): string
    {
        if (self::$link instanceof mysqli) {
            return self::$link->server_info;
        }

        return '';
    }

    /**
     * Checks if the database uses ICU regex syntax.
     *
     * @return bool true if ICU syntax is used, false otherwise
     */
    public static function isUsingICURegexSyntax(): bool
    {
        $version = self::getDatabaseVersion();

        // MariaDB uses POSIX regex syntax at every version, and names itself in
        // the version string, sometimes behind the historical 5.5.5- prefix it
        // still sends for the benefit of old clients.
        if (str_contains($version, 'MariaDB')) {
            return false;
        }

        // MySQL moved REGEXP to ICU in 8.0 and has not gone back, so the test
        // is the major version rather than a single release: matching 8.x
        // alone put MySQL 9 back on the POSIX branch.
        if (preg_match('/^(\d+)\./', $version, $matches)) {
            return (int)$matches[1] >= 8;
        }

        // MySQL < 8.0, and anything that does not announce a version.
        return false;
    }
}
