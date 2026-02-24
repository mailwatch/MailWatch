#!/usr/bin/env php
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

if (PHP_SAPI !== 'cli') {
    header('Content-type: text/plain');
}

// Edit if you changed webapp directory from default and not using command line argument to define it
// $pathToFunctions = '/opt/mailscanner/functions.php';
$pathToFunctions = __DIR__ . '/mailscanner/functions.php';

$cli_options = getopt('', ['skip-user-confirm']);

if (isset($argv) && count($argv) > 1) {
    if (empty($cli_options)) {
        $pathToFunctions = $argv[1];
    } else {
        $args = array_search('--', $argv, true);
        $args = array_splice($argv, $args ? ++$args : (count($argv) - count($cli_options)));
        // get path from command line argument if set
        $pathToFunctions = $args[0];
    }
}

if (!@is_file($pathToFunctions)) {
    exit('Error: Cannot find functions.php file in "' . $pathToFunctions . '": edit ' . __FILE__ . ' and set the right path on line ' . (__LINE__ - 17) . PHP_EOL);
}

require_once $pathToFunctions;

$link = dbconn();

$mysql_utf8_variant = [
    'utf8mb4' => ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_520_ci'],
];

/*****************************************************************
 * Start helper functions
 *****************************************************************/

function pad(string $input): string
{
    return str_pad($input, 70, '.', STR_PAD_RIGHT);
}

function executeQuery(string $sql, bool $beSilent = false): void
{
    global $link;
    try {
        if ($link->query($sql)) {
            if (!$beSilent) {
                echo color(' OK', 'green') . PHP_EOL;
            }
        } else {
            echo color(' ERROR', 'red') . PHP_EOL;
            exit('Database error: ' . $link->error . " - SQL = '$sql'" . PHP_EOL);
        }
    } catch (Exception $e) {
        echo color(' ERROR', 'red') . PHP_EOL;
        exit('Database error: ' . $e->getMessage() . " - SQL = '$sql'" . PHP_EOL);
    }
}

function check_table_exists(string $table): bool
{
    global $link;
    $sql = 'SHOW TABLES LIKE "' . $table . '"';

    return $link->query($sql)->num_rows > 0;
}

function check_column_exists(string $table, string $column): bool
{
    global $link;
    $sql = 'SHOW COLUMNS FROM `' . $table . '` LIKE "' . $column . '"';

    return $link->query($sql)->num_rows > 0;
}

function get_database_charset(): bool|string
{
    global $link;
    $sql = 'SELECT default_character_set_name
            FROM information_schema.schemata
            WHERE schema_name = "' . DB_NAME . '"';
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $result = $link->query($sql);
    $row = $result->fetch_array();
    if (null !== $row && isset($row[0])) {
        return $row[0];
    }

    return false;
}

function get_database_collation(): bool|string
{
    global $link;
    $sql = 'SELECT default_collation_name
            FROM information_schema.schemata
            WHERE schema_name = "' . DB_NAME . '"';
    $result = $link->query($sql);
    $row = $result->fetch_array();
    if (null !== $row && isset($row[0])) {
        return $row[0];
    }

    return false;
}

function check_utf8_table(string $db, string $table, string $utf8variant = 'utf8mb4'): bool
{
    global $link;
    global $mysql_utf8_variant;

    $sql = 'SELECT c.character_set_name, c.collation_name
            FROM information_schema.tables AS t, information_schema.collation_character_set_applicability AS c
            WHERE c.collation_name = t.table_collation
            AND t.table_schema = "' . $link->real_escape_string($db) . '"
            AND t.table_name = "' . $link->real_escape_string($table) . '"';
    $result = $link->query($sql);

    $table_charset = Database::mysqli_result($result, 0, 0);
    $table_collation = Database::mysqli_result($result, 0, 1);

    return
        strtolower($table_charset) === $mysql_utf8_variant[$utf8variant]['charset']
        && strtolower($table_collation) === $mysql_utf8_variant[$utf8variant]['collation']
    ;
}

function is_table_type_innodb(string $db, string $table): bool
{
    global $link;
    $sql = 'SELECT t.engine 
            FROM information_schema.tables AS t 
            WHERE t.table_schema = "' . $link->real_escape_string($db) . '" 
            AND t.table_name = "' . $link->real_escape_string($table) . '"';
    $result = $link->query($sql);

    return 'innodb' === strtolower(Database::mysqli_result($result, 0, 0));
}

function get_index_size(string $db, string $table, string $index): ?int
{
    global $link;
    $sql = 'SHOW INDEX FROM ' . $db . '.' . $table . ' WHERE Key_name = "' . $index . '"';
    $result = $link->query($sql);
    $row = $result->fetch_assoc();
    if (null === $row || null === $row['Sub_part']) {
        return null;
    }

    return (int)$row['Sub_part'];
}

function getTableIndexes(string $table): array
{
    global $link;
    $sql = 'SHOW INDEX FROM `' . $table . '`';
    $result = $link->query($sql);

    $indexes = [];
    if (false === $result || 0 === $result->num_rows) {
        return $indexes;
    }

    while ($row = $result->fetch_assoc()) {
        $indexes[] = $row['Key_name'];
    }

    return $indexes;
}

function getSqlServer(): string
{
    global $link;
    // test if mysql or mariadb is used.
    $sql = 'SELECT VERSION() as version';
    $result = $link->query($sql);
    $fetch = $result->fetch_array();
    if (!str_contains($fetch['version'], 'MariaDB')) {
        // mysql does not support aria storage engine
        return 'mysql';
    }

    return 'mariadb';
}

function getColumnInfo(string $table, string $column): bool|array|null
{
    global $link;
    $sql = 'SHOW COLUMNS FROM ' . $table . " LIKE '" . $column . "'";

    return $link->query($sql)->fetch_array();
}

function color(string $string, string $color = ''): string
{
    $after = "\033[0m";
    switch ($color) {
        case 'green':
            $before = "\033[1;32m";
            break;
        case 'lightgreen':
            $before = "\033[0;32m";
            break;
        case 'yellow':
            $before = "\033[1;33;40m";
            break;
        case 'red':
            $before = "\033[0;31m";
            break;
        default:
            $before = '';
            $after = '';
            break;
    }

    return $before . $string . $after;
}

/*****************************************************************
 * End helper functions
 *****************************************************************/

$errors = [];

// Upgrade mailwatch database
echo PHP_EOL;
echo 'MailWatch for MailScanner Database Upgrade to ' . mailwatch_version() . PHP_EOL;
echo PHP_EOL;

if (!array_key_exists('skip-user-confirm', $cli_options)) {
    echo "Have you done a full backup of your database? Type 'yes' to continue: ";
    $handle = fopen('php://stdin', 'rb');
    $line = fgets($handle);
    if ('yes' !== strtolower(trim($line))) {
        echo 'ABORTING!' . PHP_EOL;
        exit(1);
    }
    fclose($handle);

    echo PHP_EOL;
}

// Minimal PHP version check (placed after banner)
echo pad('Checking minimal PHP version >= 8.1');
if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 80100) {
    echo color(' OK', 'green') . PHP_EOL;
} else {
    echo color(' ERROR', 'red') . PHP_EOL;
    echo 'ERROR: PHP version ' . PHP_VERSION . ' detected. Minimum required is 8.1. Upgrade aborted.' . PHP_EOL;
    exit(1);
}

echo PHP_EOL;

// Test connectivity to the database
echo pad('Testing connectivity to the database');

if ($link) {
    echo color(' OK', 'green') . PHP_EOL;

    // Check minimal MySQL/MariaDB version
    echo pad(' - Checking minimal database version');
    $server_type = getSqlServer();
    $server_version = $link->server_version;

    // MySQL requires 5.7.42 (version 50742) or higher
    // MariaDB 10.4.34 (version 100434) is equivalent
    if ('mysql' === $server_type) {
        if ($server_version >= 50742) {
            echo color(' MySQL version OK', 'lightgreen') . PHP_EOL;
        } else {
            echo color(' WARNING: MySQL version < 5.7.42 not supported', 'red') . PHP_EOL;
            $errors[] = 'MySQL version ' . $link->server_info . ' is not supported. Minimum required: 5.7.42';
        }
    } else {
        // MariaDB
        if ($server_version >= 100434) {
            echo color(' MariaDB version OK', 'lightgreen') . PHP_EOL;
        } else {
            echo color(' WARNING: MariaDB version < 10.4.34 not supported', 'red') . PHP_EOL;
            $errors[] = 'MariaDB version ' . $link->server_info . ' is not supported. Minimum required: 10.4.34';
        }
    }

    // Stop if database version is not compatible
    if (!empty($errors)) {
        echo PHP_EOL;
        echo color('ERROR: Incompatible database version. Upgrade aborted.', 'red') . PHP_EOL;
        foreach ($errors as $error) {
            echo ' - ' . $error . PHP_EOL;
        }
        exit(1);
    }

    // Update schema at this point
    echo PHP_EOL;
    echo 'Updating database schema: ' . PHP_EOL;
    echo PHP_EOL;

    /*
    ** Updates to the schema for 1.3.0
    */
    $server_utf8_variant = 'utf8mb4';

    // Convert database to utf8 if not already utf8mb4 or if other charset
    echo pad(' - Convert database to UTF-8');
    if (get_database_charset() === $mysql_utf8_variant['utf8mb4']['charset'] && get_database_collation() === $mysql_utf8_variant['utf8mb4']['collation']) {
        echo color(' ALREADY DONE', 'lightgreen') . PHP_EOL;
    } else {
        $sql = 'ALTER DATABASE `' . DB_NAME .
            '` CHARACTER SET = ' . $mysql_utf8_variant[$server_utf8_variant]['charset'] .
            ' COLLATE = ' . $mysql_utf8_variant[$server_utf8_variant]['collation'];
        executeQuery($sql);
    }

    echo PHP_EOL;
    echo pad(' - Fix security issues in database');
    if (defined('IMAP_AUTOCREATE_VALID_USER') && IMAP_AUTOCREATE_VALID_USER === true) {
        $sql = "UPDATE users SET fullname = username WHERE type='U' AND password IS NULL";
        executeQuery($sql);
    } else {
        echo color(' No known security issues', 'lightgreen') . PHP_EOL;
    }

    echo PHP_EOL;

    // Drop geoip table
    echo pad(' - Drop `geoip_country` table');
    if (false === check_table_exists('geoip_country')) {
        echo color(' ALREADY DROPPED', 'lightgreen') . PHP_EOL;
    } else {
        $sql = 'DROP TABLE IF EXISTS geoip_country';
        executeQuery($sql);
    }

    // Drop spamscores table
    echo pad(' - Drop `spamscores` table');
    if (false === check_table_exists('spamscores')) {
        echo color(' ALREADY DROPPED', 'lightgreen') . PHP_EOL;
    } else {
        $sql = 'DROP TABLE IF EXISTS spamscores';
        executeQuery($sql);
    }

    // Add autorelease table if not exist (1.2RC2)
    echo pad(' - Add autorelease table to `' . DB_NAME . '` database');
    if (true === check_table_exists('autorelease')) {
        echo color(' ALREADY EXIST', 'lightgreen') . PHP_EOL;
    } else {
        $sql = 'CREATE TABLE IF NOT EXISTS autorelease (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            msg_id VARCHAR(255) COLLATE utf8mb4_unicode_520_ci NOT NULL,
            uid VARCHAR(255) COLLATE utf8mb4_unicode_520_ci NOT NULL,
            PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci';
        executeQuery($sql);
    }

    // Add mtalog_ids table if not exist (if upgrade from < 1.2.0)
    echo pad(' - Add mtalog_ids table to `' . DB_NAME . '` database');
    if (true === check_table_exists('mtalog_ids')) {
        echo color(' ALREADY EXIST', 'lightgreen') . PHP_EOL;
    } else {
        $sql = 'CREATE TABLE IF NOT EXISTS mtalog_ids (
            smtpd_id VARCHAR(20) CHARACTER SET ascii DEFAULT NULL,
            smtp_id VARCHAR(20) CHARACTER SET ascii DEFAULT NULL,
            UNIQUE KEY mtalog_ids_idx (smtpd_id,smtp_id),
            KEY smtpd_id (smtpd_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci';
        executeQuery($sql);
    }

    // Update users table schema for password-reset feature
    echo pad(' - Add resetid, resetexpire and lastreset fields in `users` table');
    if (false === check_column_exists('users', 'resetid')) {
        $sql = 'ALTER TABLE users ADD COLUMN (
            resetid VARCHAR(32),
            resetexpire BIGINT(20),
            lastreset BIGINT(20)
            );';
        executeQuery($sql);
    } else {
        echo color(' ALREADY EXIST', 'lightgreen') . PHP_EOL;
    }

    // Update users table schema for login_expiry, last_login and individual login_timeout feature
    echo pad(' - Add login_expiry and login_timeout fields in `users` table');
    if (false === check_column_exists('users', 'login_expiry')) {
        $sql = "ALTER TABLE users ADD COLUMN (
            login_expiry BIGINT(20) COLLATE utf8mb4_unicode_520_ci DEFAULT '-1',
            last_login BIGINT(20) COLLATE utf8mb4_unicode_520_ci DEFAULT '-1',
            login_timeout SMALLINT(5) COLLATE utf8mb4_unicode_520_ci DEFAULT '-1'
            );";
        executeQuery($sql);
    } else {
        echo color(' ALREADY EXIST', 'lightgreen') . PHP_EOL;
    }

    // Update users table schema for unique id
    echo pad(' - Add id field in `users` table');
    if (false === check_column_exists('users', 'id')) {
        $sql = 'ALTER TABLE users ADD id BIGINT NOT NULL AUTO_INCREMENT FIRST, ADD UNIQUE (id);';
        executeQuery($sql);
    } else {
        echo color(' ALREADY EXIST', 'lightgreen') . PHP_EOL;
    }

    // Update users table schema to prevent empty user type
    echo pad(' - Fix schema for type field in `users` table');
    $user_type_info = getColumnInfo('users', 'type');
    if ('No' !== $user_type_info['Null']) {
        $sql = "ALTER TABLE users CHANGE type type enum('A','D','U','R','H') COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'U';";
        executeQuery($sql);
    } else {
        echo color(' ALREADY DONE', 'lightgreen') . PHP_EOL;
    }
    echo PHP_EOL;

    // Truncate needed for VARCHAR fields used as PRIMARY or FOREIGN KEY when using utf8mb4

    // Table audit_log
    echo pad(' - Fix schema for username field in `audit_log` table');
    $audit_log_user_info = getColumnInfo('audit_log', 'user');
    if ('varchar(191)' !== $audit_log_user_info['Type']) {
        $sql = "ALTER TABLE audit_log CHANGE user user VARCHAR(191) NOT NULL DEFAULT ''";
        executeQuery($sql);
    } else {
        echo color(' ALREADY DONE', 'lightgreen') . PHP_EOL;
    }
    unset($audit_log_user_info);

    echo pad(' - Fix schema for ipv6 support in `audit_log` table');
    $audit_log_ipaddr_info = getColumnInfo('audit_log', 'ip_address');
    if ('varchar(45)' !== $audit_log_ipaddr_info['Type']) {
        $sql = "ALTER TABLE audit_log CHANGE ip_address ip_address VARCHAR(45) NOT NULL DEFAULT ''";
        executeQuery($sql);
    } else {
        echo color(' ALREADY DONE', 'lightgreen') . PHP_EOL;
    }
    unset($audit_log_user_info);

    // Migrate blacklist to blocklist (terminology change)
    echo pad(' - Rename `blacklist` table to `blocklist`');
    if (check_table_exists('blacklist') && !check_table_exists('blocklist')) {
        $sql = 'RENAME TABLE blacklist TO blocklist';
        executeQuery($sql);
    } elseif (check_table_exists('blocklist')) {
        echo color(' ALREADY DONE', 'lightgreen') . PHP_EOL;
    } else {
        echo color(' N/A', 'yellow') . PHP_EOL;
    }

    // Table blocklist
    echo pad(' - Fix schema for id field in `blocklist` table');
    if (check_table_exists('blocklist')) {
        $blocklist_id_info = getColumnInfo('blocklist', 'id');
        if ('bigint(20) unsigned' !== strtolower($blocklist_id_info['Type']) || 'NO' !== strtoupper($blocklist_id_info['Null']) || 'auto_increment' !== strtolower($blocklist_id_info['Extra'])) {
            $sql = 'ALTER TABLE blocklist CHANGE id id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT';
            executeQuery($sql);
        } else {
            echo color(' ALREADY DONE', 'lightgreen') . PHP_EOL;
        }
        unset($blocklist_id_info);
    } else {
        echo color(' N/A', 'yellow') . PHP_EOL;
    }

    // Migrate whitelist to allowlist (terminology change)
    echo pad(' - Rename `whitelist` table to `allowlist`');
    if (check_table_exists('whitelist') && !check_table_exists('allowlist')) {
        $sql = 'RENAME TABLE whitelist TO allowlist';
        executeQuery($sql);
    } elseif (check_table_exists('allowlist')) {
        echo color(' ALREADY DONE', 'lightgreen') . PHP_EOL;
    } else {
        echo color(' N/A', 'yellow') . PHP_EOL;
    }

    // Table allowlist
    echo pad(' - Fix schema for id field in `allowlist` table');
    if (check_table_exists('allowlist')) {
        $allowlist_id_info = getColumnInfo('allowlist', 'id');
        if ('bigint(20) unsigned' !== strtolower($allowlist_id_info['Type']) || 'NO' !== strtoupper($allowlist_id_info['Null']) || 'auto_increment' !== strtolower($allowlist_id_info['Extra'])) {
            $sql = 'ALTER TABLE allowlist CHANGE id id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT';
            executeQuery($sql);
        } else {
            echo color(' ALREADY DONE', 'lightgreen') . PHP_EOL;
        }
        unset($allowlist_id_info);
    } else {
        echo color(' N/A', 'yellow') . PHP_EOL;
    }

    // username lenght to 191
    echo pad(' - Fix schema for username field in `users` table');
    $users_username_info = getColumnInfo('users', 'username');
    if ('varchar(191)' !== $users_username_info['Type']) {
        $sql = "ALTER TABLE users CHANGE username username VARCHAR(191) NOT NULL DEFAULT ''";
        executeQuery($sql);
    } else {
        echo color(' ALREADY DONE', 'lightgreen') . PHP_EOL;
    }

    echo pad(' - Fix schema for username field in `user_filters` table');
    $user_filters_username_info = getColumnInfo('users', 'username');
    if ('varchar(191)' !== $user_filters_username_info['Type']) {
        $sql = "ALTER TABLE user_filters CHANGE username username VARCHAR(191) NOT NULL DEFAULT ''";
        executeQuery($sql);
    } else {
        echo color(' ALREADY DONE', 'lightgreen') . PHP_EOL;
    }

    // Table user_filters spam score to float
    echo pad(' - Fix schema for spamscore field in `users` table');
    $users_spamscore_info = getColumnInfo('users', 'spamscore');
    if ('float' !== $users_spamscore_info['Type']) {
        $sql = "ALTER TABLE users CHANGE spamscore spamscore FLOAT DEFAULT '0'";
        executeQuery($sql);
    } else {
        echo color(' ALREADY DONE', 'lightgreen') . PHP_EOL;
    }

    echo pad(' - Fix schema for highspamscore field in `users` table');
    $users_highspamscore_info = getColumnInfo('users', 'highspamscore');
    if ('float' !== $users_highspamscore_info['Type']) {
        $sql = "ALTER TABLE users CHANGE highspamscore highspamscore FLOAT DEFAULT '0'";
        executeQuery($sql);
    } else {
        echo color(' ALREADY DONE', 'lightgreen') . PHP_EOL;
    }

    // Change timestamp to only be updated on creation to fix messages not being deleted from maillog.
    // We don't need a default / on update value for the timestamp field in the maillog table because we only change it in mailwatch.pm
    // where we use the current system time from perl (not mysql function) as value. So we can remove it.
    // Initial remove because MySQL in version < 5.6 cannot handle two columns with CURRENT_TIMESTAMP in DEFAULT
    echo pad(' - Fix schema for timestamp field in `maillog` table');
    $maillog_timestamp_info = getColumnInfo('maillog', 'timestamp');
    if (null !== $maillog_timestamp_info['Default'] || '' !== $maillog_timestamp_info['Extra']) {
        // Set NULL default on timestamp column
        $sql = 'ALTER TABLE maillog CHANGE timestamp timestamp TIMESTAMP NULL DEFAULT NULL;';
        executeQuery($sql);
    } else {
        echo color(' ALREADY DONE', 'lightgreen') . PHP_EOL;
    }
    unset($maillog_timestamp_info);

    // Fix schema for nameinfected to allow for >9 entries.  #981
    echo pad(' - Fix schema for nameinfected in `maillog` table');
    $maillog_nameinfected = getColumnInfo('maillog', 'nameinfected');
    if ('tinyint(2)' !== $maillog_nameinfected['Type']) {
        $sql = 'ALTER TABLE maillog CHANGE nameinfected nameinfected TINYINT(2) DEFAULT 0';
        executeQuery($sql);
    } else {
        echo color(' ALREADY DONE', 'lightgreen') . PHP_EOL;
    }
    unset($maillog_nameinfected);

    // Revert back some tables to the right values due to previous errors in upgrade.php

    // Table users password to 255
    echo pad(' - Fix schema for password field in `users` table');
    $users_password_info = getColumnInfo('users', 'password');
    if ('varchar(255)' !== $users_password_info['Type']) {
        $sql = 'ALTER TABLE `users` CHANGE `password` `password` VARCHAR(255) DEFAULT NULL';
        executeQuery($sql);
    } else {
        echo color(' ALREADY DONE', 'lightgreen') . PHP_EOL;
    }
    unset($users_password_info);

    // Table users fullname to 255
    echo pad(' - Fix schema for fullname field in `users` table');
    $users_fullname_info = getColumnInfo('users', 'fullname');
    if ('varchar(255)' !== $users_fullname_info['Type']) {
        $sql = "ALTER TABLE `users` CHANGE `fullname` `fullname` VARCHAR(255) NOT NULL DEFAULT ''";
        executeQuery($sql);
    } else {
        echo color(' ALREADY DONE', 'lightgreen') . PHP_EOL;
    }
    unset($users_fullname_info);

    // Table mcp_rules
    echo pad(' - Fix schema for rule and rule_desc field in `mcp_rules` table');
    $mcp_rules_rule_info = getColumnInfo('mcp_rules', 'rule');
    $mcp_rules_rule_desc_info = getColumnInfo('mcp_rules', 'rule_desc');
    if ('varchar(191)' !== $mcp_rules_rule_info['Type'] || 'varchar(512)' !== $mcp_rules_rule_desc_info['Type']) {
        $sql = "ALTER TABLE mcp_rules CHANGE rule rule VARCHAR(191) NOT NULL DEFAULT '', CHANGE rule_desc rule_desc VARCHAR(512) NOT NULL DEFAULT '';";
        executeQuery($sql);
    } else {
        echo color(' ALREADY DONE', 'lightgreen') . PHP_EOL;
    }
    unset($mcp_rules_rule_desc_info);

    echo PHP_EOL;

    // Table sa_rules
    echo pad(' - Fix schema for rule and rule_desc field in `sa_rules` table');
    $sa_rules_rule_info = getColumnInfo('sa_rules', 'rule');
    $sa_rules_rule_desc_info = getColumnInfo('sa_rules', 'rule_desc');
    if ('varchar(191)' !== $sa_rules_rule_info['Type'] || 'varchar(512)' !== $sa_rules_rule_desc_info['Type']) {
        $sql = "ALTER TABLE sa_rules CHANGE rule rule VARCHAR(191) NOT NULL DEFAULT '', CHANGE rule_desc rule_desc VARCHAR(512) NOT NULL DEFAULT ''";
        executeQuery($sql);
    } else {
        echo color(' ALREADY DONE', 'lightgreen') . PHP_EOL;
    }
    unset($sa_rules_rule_desc_info);

    echo PHP_EOL;

    // Cleanup orphaned user_filters
    echo pad(' - Cleanup orphaned user_filters');
    $sql = 'DELETE FROM user_filters WHERE username NOT IN (SELECT username FROM users)';
    executeQuery($sql);

    // Add new column and index to audit_log table
    echo pad(' - Add id field and primary key to `audit_log` table');
    if (true === check_column_exists('audit_log', 'id')) {
        echo color(' ALREADY DONE', 'lightgreen') . PHP_EOL;
    } else {
        $sql = 'ALTER TABLE audit_log ADD id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (id)';
        executeQuery($sql);
    }

    // Add new column and index to inq table
    echo pad(' - Add inq_id field and primary key to `inq` table');
    if (true === check_column_exists('inq', 'inq_id')) {
        echo color(' ALREADY DONE', 'lightgreen') . PHP_EOL;
    } else {
        $sql = 'ALTER TABLE inq ADD inq_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (inq_id)';
        executeQuery($sql);
    }

    // Add new column and index to maillog table
    echo pad(' - Add maillog_id field and primary key to `maillog` table');
    if (true === check_column_exists('maillog', 'maillog_id')) {
        echo color(' ALREADY DONE', 'lightgreen') . PHP_EOL;
    } else {
        $sql = 'ALTER TABLE maillog ADD maillog_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (maillog_id)';
        executeQuery($sql);
    }

    // Add new column to maillog table
    echo pad(' - Add rblspamreport field to `maillog` table');
    if (true === check_column_exists('maillog', 'rblspamreport')) {
        echo color(' ALREADY DONE', 'lightgreen') . PHP_EOL;
    } else {
        $sql = 'ALTER TABLE `maillog` ADD `rblspamreport` MEDIUMTEXT COLLATE utf8mb4_unicode_520_ci DEFAULT NULL';
        executeQuery($sql);
    }

    // Add new token column to maillog table
    echo pad(' - Add token field to `maillog` table');
    if (true === check_column_exists('maillog', 'token')) {
        echo color(' ALREADY DONE', 'lightgreen') . PHP_EOL;
    } else {
        $sql = 'ALTER TABLE maillog ADD token CHAR(64) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL';
        executeQuery($sql);
    }

    // Add new released column to maillog table
    echo pad(' - Add released field to `maillog` table');
    if (true === check_column_exists('maillog', 'released')) {
        echo color(' ALREADY DONE', 'lightgreen') . PHP_EOL;
    } else {
        $sql = "ALTER TABLE `maillog` ADD `released` TINYINT(1) DEFAULT '0'";
        executeQuery($sql);
    }

    echo pad(' - Add last_update field to `maillog` table');
    if (false === check_column_exists('maillog', 'last_update')) {
        $sql = 'ALTER TABLE `maillog` ADD `last_update` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP';
        executeQuery($sql);
    } else {
        echo color(' ALREADY DONE', 'lightgreen') . PHP_EOL;
    }

    // Add new salearn column to maillog table
    echo pad(' - Add salearn field to `maillog` table');
    if (true === check_column_exists('maillog', 'salearn')) {
        echo color(' ALREADY DONE', 'lightgreen') . PHP_EOL;
    } else {
        $sql = "ALTER TABLE `maillog` ADD `salearn` TINYINT(1) DEFAULT '0'";
        executeQuery($sql);
    }

    // Add new messageid column to maillog table
    echo pad(' - Add messageid field to `maillog` table');
    if (true === check_column_exists('maillog', 'messageid')) {
        echo color(' ALREADY DONE', 'lightgreen') . PHP_EOL;
    } else {
        $sql = 'ALTER TABLE maillog ADD messageid MEDIUMTEXT COLLATE utf8mb4_unicode_520_ci DEFAULT NULL';
        executeQuery($sql);
    }

    // Rename columns from whitelist/blacklist to allowlist/blocklist terminology
    echo pad(' - Rename spamwhitelisted to spamallowlisted in `maillog` table');
    if (check_column_exists('maillog', 'spamwhitelisted') && !check_column_exists('maillog', 'spamallowlisted')) {
        $sql = 'ALTER TABLE maillog CHANGE spamwhitelisted spamallowlisted TINYINT(1) DEFAULT 0';
        executeQuery($sql);
    } elseif (check_column_exists('maillog', 'spamallowlisted')) {
        echo color(' ALREADY DONE', 'lightgreen') . PHP_EOL;
    } else {
        echo color(' N/A', 'yellow') . PHP_EOL;
    }

    echo pad(' - Rename spamblacklisted to spamblocklisted in `maillog` table');
    if (check_column_exists('maillog', 'spamblacklisted') && !check_column_exists('maillog', 'spamblocklisted')) {
        $sql = 'ALTER TABLE maillog CHANGE spamblacklisted spamblocklisted TINYINT(1) DEFAULT 0';
        executeQuery($sql);
    } elseif (check_column_exists('maillog', 'spamblocklisted')) {
        echo color(' ALREADY DONE', 'lightgreen') . PHP_EOL;
    } else {
        echo color(' N/A', 'yellow') . PHP_EOL;
    }

    echo pad(' - Rename mcpwhitelisted to mcpallowlisted in `maillog` table');
    if (check_column_exists('maillog', 'mcpwhitelisted') && !check_column_exists('maillog', 'mcpallowlisted')) {
        $sql = 'ALTER TABLE maillog CHANGE mcpwhitelisted mcpallowlisted TINYINT(1) DEFAULT 0';
        executeQuery($sql);
    } elseif (check_column_exists('maillog', 'mcpallowlisted')) {
        echo color(' ALREADY DONE', 'lightgreen') . PHP_EOL;
    } else {
        echo color(' N/A', 'yellow') . PHP_EOL;
    }

    echo pad(' - Rename mcpblacklisted to mcpblocklisted in `maillog` table');
    if (check_column_exists('maillog', 'mcpblacklisted') && !check_column_exists('maillog', 'mcpblocklisted')) {
        $sql = 'ALTER TABLE maillog CHANGE mcpblacklisted mcpblocklisted TINYINT(1) DEFAULT 0';
        executeQuery($sql);
    } elseif (check_column_exists('maillog', 'mcpblocklisted')) {
        echo color(' ALREADY DONE', 'lightgreen') . PHP_EOL;
    } else {
        echo color(' N/A', 'yellow') . PHP_EOL;
    }

    // Check for missing tokens in maillog table and add them back QUARANTINE_REPORT_DAYS
    echo pad(' - Check for missing tokens in `maillog` table');
    if (defined('QUARANTINE_REPORT_DAYS')) {
        $report_days = QUARANTINE_REPORT_DAYS;
    } else {
        // Missing, but let's keep going...
        $report_days = 7;
    }
    $sql = 'SELECT `id`,`token` FROM `maillog` WHERE `date` >= DATE_SUB(CURRENT_DATE(), INTERVAL ' . $report_days . ' DAY)';
    $result = dbquery($sql);
    $rows = $result->num_rows;
    $countTokenGenerated = 0;
    if ($rows > 0) {
        while ($row = $result->fetch_object()) {
            if (null === $row->token) {
                $sql = 'UPDATE `maillog` SET `token`=\'' . generateToken() . '\' WHERE `id`=\'' . trim($row->id) . '\'';
                executeQuery($sql, true);
                ++$countTokenGenerated;
            }
        }
        echo color(' DONE', 'lightgreen') . PHP_EOL;
        echo '   ' . $countTokenGenerated . ' token generated' . PHP_EOL;
    } else {
        echo color(' NOTHING FOUND', 'lightgreen') . PHP_EOL;
    }

    // Add new column and index to mtalog table
    echo pad(' - Add mtalog_id field and primary key to `mtalog` table');
    if (true === check_column_exists('mtalog', 'mtalog_id')) {
        echo color(' ALREADY DONE', 'lightgreen') . PHP_EOL;
    } else {
        $sql = 'ALTER TABLE `mtalog` ADD `mtalog_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`mtalog_id`)';
        executeQuery($sql);
    }

    // Add new column and add to unique key in mtalog table
    echo pad(' - Add to_address field to `mtalog` table and add to unique key');
    if (true === check_column_exists('mtalog', 'to_address')) {
        echo color(' ALREADY DONE', 'lightgreen') . PHP_EOL;
    } else {
        $sql = 'ALTER TABLE `mtalog` ADD `to_address` MEDIUMTEXT DEFAULT NULL, DROP KEY `mtalog_uniq`, ADD UNIQUE KEY `mtalog_uniq` (`timestamp`,`host`(10),`type`(10),`msg_id`,`relay`(20),`to_address`(64))';
        executeQuery($sql);
    }

    // Add new column and index to outq table
    echo pad(' - Add mtalog_id field and primary key to `outq` table');
    if (true === check_column_exists('outq', 'outq_id')) {
        echo color(' ALREADY DONE', 'lightgreen') . PHP_EOL;
    } else {
        $sql = 'ALTER TABLE outq ADD outq_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (outq_id)';
        executeQuery($sql);
    }

    // Add new column and index to saved_filters table
    echo pad(' - Add id field and primary key to `saved_filters` table');
    if (true === check_column_exists('saved_filters', 'id')) {
        echo color(' ALREADY DONE', 'lightgreen') . PHP_EOL;
    } else {
        $sql = 'ALTER TABLE saved_filters ADD id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (id)';
        executeQuery($sql);
    }

    // Add new column and index to user_filters table
    echo pad(' - Add mtalog_id field and primary key to `user_filters` table');
    if (true === check_column_exists('user_filters', 'id')) {
        echo color(' ALREADY DONE', 'lightgreen') . PHP_EOL;
    } else {
        $sql = 'ALTER TABLE user_filters ADD id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (id)';
        executeQuery($sql);
    }

    echo PHP_EOL;

    // Fix existing index size for utf8mb4 conversion
    $too_big_indexes = [
        'maillog_from_idx',
        'maillog_to_idx',
    ];

    foreach ($too_big_indexes as $item) {
        echo pad(' - Dropping too big index `' . $item . '` on table `maillog`');
        if (get_index_size(DB_NAME, 'maillog', $item) > 191) {
            $sql = 'ALTER TABLE `maillog` DROP INDEX `' . $item . '`';
            executeQuery($sql);
        } else {
            echo color(' ALREADY DONE', 'lightgreen') . PHP_EOL;
        }
    }

    echo PHP_EOL;

    $utf8_tables = [
        'audit_log',
        'autorelease',
        'blocklist',
        'inq',
        'maillog',
        'mcp_rules',
        'mtalog',
        'mtalog_ids',
        'outq',
        'saved_filters',
        'sa_rules',
        'users',
        'user_filters',
        'allowlist',
    ];

    // Convert tables to utf8 using $utf8_tables array
    foreach ($utf8_tables as $table) {
        echo pad(' - Convert table `' . $table . '` to ' . $server_utf8_variant . '');
        if (false === check_table_exists($table)) {
            echo ' DO NOT EXISTS' . PHP_EOL;
        } elseif (false === check_utf8_table(DB_NAME, $table, $server_utf8_variant)) {
            $sql = 'ALTER TABLE `' . $table .
                '` CONVERT TO CHARACTER SET ' . $mysql_utf8_variant[$server_utf8_variant]['charset'] .
                ' COLLATE ' . $mysql_utf8_variant[$server_utf8_variant]['collation'];
            executeQuery($sql);
        } else {
            echo color(' ALREADY CONVERTED', 'lightgreen') . PHP_EOL;
        }
    }

    echo PHP_EOL;

    // Convert tables to InnoDB using $utf8_tables array
    foreach ($utf8_tables as $table) {
        echo pad(' - Convert table `' . $table . '` to InnoDB');
        if (false === check_table_exists($table)) {
            echo ' DO NOT EXISTS' . PHP_EOL;
        } elseif (false === is_table_type_innodb(DB_NAME, $table)) {
            $sql = 'ALTER TABLE `' . $table . '` ENGINE = InnoDB';
            executeQuery($sql);
        } else {
            echo color(' ALREADY CONVERTED', 'lightgreen') . PHP_EOL;
        }
    }

    // check for missing indexes
    $indexes = [
        'audit_log' => [
            'audit_log_timestamp' => [
                'fields' => '(`timestamp`)',
                'type' => 'KEY',
            ],
        ],
        'mtalog_ids' => [
            'mtalog_ids_smtp_id' => [
                'fields' => '(`smtp_id`)',
                'type' => 'KEY',
            ],
        ],
        'maillog' => [
            'maillog_datetime_idx' => [
                'fields' => '(`date`,`time`)',
                'type' => 'KEY',
            ],
            'maillog_id_idx' => [
                'fields' => '(`id`(20))',
                'type' => 'KEY',
            ],
            'maillog_clientip_idx' => [
                'fields' => '(`clientip`(20))',
                'type' => 'KEY',
            ],
            'maillog_from_idx' => [
                'fields' => '(`from_address`(191))',
                'type' => 'KEY',
            ],
            'maillog_to_idx' => [
                'fields' => '(`to_address`(191))',
                'type' => 'KEY',
            ],
            'maillog_host' => [
                'fields' => '(`hostname`(30))',
                'type' => 'KEY',
            ],
            'from_domain_idx' => [
                'fields' => '(`from_domain`(50))',
                'type' => 'KEY',
            ],
            'to_domain_idx' => [
                'fields' => '(`to_domain`(50))',
                'type' => 'KEY',
            ],
            'maillog_quarantined' => [
                'fields' => '(`quarantined`)',
                'type' => 'KEY',
            ],
            'timestamp_idx' => [
                'fields' => '(`timestamp`)',
                'type' => 'KEY',
            ],
            'subject_idx' => [
                'fields' => '(`subject`)',
                'type' => 'FULLTEXT',
            ],
        ],
    ];

    foreach ($indexes as $table => $indexlist) {
        echo PHP_EOL . pad(' - Search for missing indexes on table `' . $table . '`') . color(
            ' DONE',
            'green'
        ) . PHP_EOL;
        $existingIndexes = getTableIndexes($table);
        foreach ($indexlist as $indexname => $indexValue) {
            if (!in_array($indexname, $existingIndexes, true)) {
                echo pad(' - Adding missing index `' . $indexname . '` on table `' . $table . '`');
                $sql = 'ALTER TABLE `' . $table .
                    '` ADD ' . $indexValue['type'] . ' `' . $indexname . '` ' .
                    $indexValue['fields'] .
                    ';';
                executeQuery($sql);
            }
        }
    }
    dbclose();
} else {
    echo color(' FAILED', 'red') . PHP_EOL;
    $errors[] = 'Database connection failed: ' . $link->error;
}
echo PHP_EOL;

echo 'Checking for obsolete files: ' . PHP_EOL;
echo PHP_EOL;

if (file_exists(MAILWATCH_HOME . '/images/cache/')) {
    $result = rmdir(MAILWATCH_HOME . '/images/cache/');
    if (true === $result) {
        echo pad(' - Cache dir is still present. Removed it') . color(' INFO', 'lightgreen') . PHP_EOL;
    } else {
        echo pad(' - Cache dir is still present but removing it failed') . color(' ERROR', 'red') . PHP_EOL;
    }
} else {
    echo pad(' - Cache dir already removed') . color(' OK', 'green') . PHP_EOL;
}
echo PHP_EOL;

// Check MailScanner settings
echo 'Checking MailScanner.conf settings: ' . PHP_EOL;

echo PHP_EOL;

if (!is_file(MS_CONFIG_DIR . 'MailScanner.conf')) {
    $err_msg = 'MailScanner.conf: cannot find file on path "' . MS_CONFIG_DIR . 'MailScanner.conf"';
    echo pad(' - ' . $err_msg) . color(' ERROR', 'red') . PHP_EOL;
    $errors[] = $err_msg;
} else {
    $check_settings = [
        'QuarantineWholeMessage' => 'yes',
        'QuarantineWholeMessagesAsQueueFiles' => 'no',
        'DetailedSpamReport' => 'yes',
        'IncludeScoresInSpamAssassinReport' => 'yes',
        'SpamActions' => 'store',
        'HighScoringSpamActions' => 'store',
        'AlwaysLookedUpLast' => '&MailWatchLogging',
    ];

    foreach ($check_settings as $setting => $value) {
        echo pad(" - $setting ");
        if (preg_match('/' . $value . '/', get_conf_var($setting))) {
            echo color(' OK', 'green') . PHP_EOL;
        } else {
            echo ' ' . color('WARNING', 'yellow') . PHP_EOL;
            $errors[] = "MailScanner.conf: $setting != $value (=" . get_conf_var($setting) . ')';
        }
    }
}
echo PHP_EOL;

// Check configuration for missing entries
echo 'Checking conf.php configuration entry: ' . PHP_EOL;
echo PHP_EOL;
$checkConfigEntries = checkConfVariables();
if (0 === $checkConfigEntries['needed']['count']) {
    echo pad(' - All mandatory entries are present') . color(' OK', 'green') . PHP_EOL;
} else {
    foreach ($checkConfigEntries['needed']['list'] as $missingConfigEntry) {
        echo pad(" - $missingConfigEntry ") . ' ' . color('WARNING', 'yellow') . PHP_EOL;
        $errors[] = 'conf.php: missing configuration entry "' . $missingConfigEntry . '"';
    }
}

if (0 === $checkConfigEntries['obsolete']['count']) {
    echo pad(' - All obsolete entries are already removed') . color(' OK', 'green') . PHP_EOL;
} else {
    foreach ($checkConfigEntries['obsolete']['list'] as $obsoleteConfigEntry) {
        echo pad(" - $obsoleteConfigEntry ") . ' ' . color('WARNING', 'yellow') . PHP_EOL;
        $errors[] = 'conf.php: obsolete configuration entry "' . $obsoleteConfigEntry . '" still present';
    }
}

if (0 === $checkConfigEntries['optional']['count']) {
    echo pad(' - All optional entries are already present') . color(' OK', 'green') . PHP_EOL;
} else {
    foreach ($checkConfigEntries['optional']['list'] as $optionalConfigEntry => $detail) {
        echo pad(" - optional $optionalConfigEntry ") . ' ' . color('INFO', 'lightgreen') . PHP_EOL;
        $errors[] = 'conf.php: optional configuration entry "' . $optionalConfigEntry . '" is missing, ' . $detail['description'];
    }
}

echo PHP_EOL;

/* Check configuration for syntactically wrong entries */
// IMAGES_DIR need both leading and trailing slash
if (!str_starts_with(IMAGES_DIR, '/') || !str_ends_with(IMAGES_DIR, '/')) {
    $err_msg = 'conf.php: IMAGES_DIR must start and end with a slash';
    echo pad(' - ' . $err_msg) . color(' ERROR', 'red') . PHP_EOL;
    $errors[] = $err_msg;
}
// MAILWATCH_HOSTURL don't need trailing slash
if (str_ends_with(MAILWATCH_HOSTURL, '/')) {
    $err_msg = 'conf.php: MAILWATCH_HOSTURL must not end with a slash';
    echo pad(' - ' . $err_msg) . color(' ERROR', 'red') . PHP_EOL;
    $errors[] = $err_msg;
}

// Error messages
if (!empty($errors)) {
    echo color('*** ERROR/WARNING SUMMARY ***', 'yellow') . PHP_EOL;
    foreach ($errors as $error) {
        echo $error . PHP_EOL;
    }
    echo PHP_EOL;
}
