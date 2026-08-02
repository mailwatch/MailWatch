<?php

declare(strict_types=1);

/*
 * The handful of mailscanner/functions.php helpers that extracted classes still
 * call, defined for tests.
 *
 * functions.php itself cannot be loaded here: it requires conf.php and opens a
 * database connection. Each definition is guarded, so a test that runs after
 * one of these functions has been provided for real does not redefine it.
 */

if (!function_exists('__')) {
    /**
     * Returns the key, which is what the real translator does for a key that no
     * language file defines.
     */
    function __(string $string, bool $useSystemLang = false): string
    {
        return $string;
    }
}

if (!function_exists('get_virus_conf')) {
    /**
     * Answers false for a scanner absent from virus.scanners.conf, as the real
     * one does.
     */
    function get_virus_conf(string $scanner): string|false
    {
        return $GLOBALS['mailwatch_test_virus_conf'][$scanner] ?? '/usr/local/bin/' . $scanner;
    }
}

if (!function_exists('get_conf_var')) {
    function get_conf_var(string $name, bool $useSystemLang = false): string
    {
        return $GLOBALS['mailwatch_test_conf'][$name] ?? '';
    }
}

if (!function_exists('disableBrowserCache')) {
    function disableBrowserCache(): void
    {
    }
}

if (!function_exists('checkPrivilegeChange')) {
    function checkPrivilegeChange(mixed $username): bool
    {
        return false;
    }
}

if (!function_exists('checkLoginExpiry')) {
    function checkLoginExpiry(mixed $username): bool
    {
        return false;
    }
}

if (!function_exists('updateLoginExpiry')) {
    function updateLoginExpiry(mixed $username): void
    {
    }
}
