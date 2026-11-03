<?php

/**
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

/*
 * Failed login counter per client IP and username, stored in the login_failures table.
 * Without the table (upgrade.php not run yet) nothing is counted and nobody is locked out.
 */

/**
 * @return array [max failures, lockout in seconds], max failures 0 disables the lockout
 */
function login_throttle_limits()
{
    return [
        defined('LOGIN_MAX_FAILURES') ? (int)LOGIN_MAX_FAILURES : 5,
        (defined('LOGIN_LOCKOUT_MINUTES') ? (int)LOGIN_LOCKOUT_MINUTES : 15) * 60,
    ];
}

/**
 * @param int $failures       failures counted so far
 * @param int $lastFailure    time of the last failure
 * @param int $now            current time
 * @param int $maxFailures    failures that lock the key, 0 never locks
 * @param int $lockoutSeconds how long the lock lasts after the last failure
 *
 * @return bool
 */
function login_throttle_is_locked_at($failures, $lastFailure, $now, $maxFailures, $lockoutSeconds)
{
    return $maxFailures > 0 && $failures >= $maxFailures && $now - $lastFailure < $lockoutSeconds;
}

/**
 * The username is stored hashed: users sometimes type their password in the username field.
 *
 * @param string $username
 *
 * @return string
 */
function login_throttle_user_hash($username)
{
    // usernames compare case-insensitively in the users table
    return sha1(strtolower(trim($username)));
}

/**
 * @param string $sql
 *
 * @return mysqli_result|bool false when the login_failures table does not exist
 */
function login_throttle_query($sql)
{
    try {
        return dbquery($sql);
    } catch (mysqli_sql_exception $e) {
        // 1146: table does not exist
        if (1146 === $e->getCode()) {
            return false;
        }
        throw $e;
    }
}

/**
 * @param string $username
 *
 * @return string SQL condition matching the row of the current client and $username
 */
function login_throttle_where($username)
{
    return "ip = '" . safe_value(getHTTPClientIP()) . "' AND username_hash = '" . login_throttle_user_hash($username) . "'";
}

/**
 * @param string $username
 *
 * @return bool true when the current client must not try $username again yet
 */
function login_throttle_is_locked($username)
{
    list($maxFailures, $lockoutSeconds) = login_throttle_limits();
    if ($maxFailures <= 0) {
        return false;
    }
    $result = login_throttle_query('SELECT failures, last_failure FROM login_failures WHERE ' . login_throttle_where($username));
    if (false === $result || 0 === $result->num_rows) {
        return false;
    }
    $row = $result->fetch_assoc();

    return login_throttle_is_locked_at((int)$row['failures'], (int)$row['last_failure'], time(), $maxFailures, $lockoutSeconds);
}

/**
 * Counts a failure of the current client on $username; a failure after the lockout period starts a new count.
 *
 * @param string $username
 */
function login_throttle_record_failure($username)
{
    list($maxFailures, $lockoutSeconds) = login_throttle_limits();
    if ($maxFailures <= 0) {
        return;
    }
    $now = time();
    // failures is assigned first, so it still compares with the previous last_failure
    login_throttle_query(
        "INSERT INTO login_failures (ip, username_hash, failures, last_failure) VALUES ('"
        . safe_value(getHTTPClientIP()) . "', '" . login_throttle_user_hash($username) . "', 1, $now)
        ON DUPLICATE KEY UPDATE failures = IF(last_failure <= " . ($now - $lockoutSeconds) . ', 1, failures + 1), last_failure = ' . $now
    );
}

/**
 * @param string $username
 */
function login_throttle_clear($username)
{
    login_throttle_query('DELETE FROM login_failures WHERE ' . login_throttle_where($username));
}
