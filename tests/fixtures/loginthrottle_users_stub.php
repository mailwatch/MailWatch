<?php

/*
 * Stand-in for dbquery() and safe_value() over a users table that holds 'admin' and "o'brien@example.com".
 * Like the utf8_unicode_ci collation it ignores case and the zero width space when it compares usernames.
 */

function safe_value($value)
{
    return addslashes($value);
}

class LoginThrottleUsersStubResult
{
    public $num_rows;
    private $rows;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
        $this->num_rows = count($rows);
    }

    public function fetch_assoc()
    {
        return array_shift($this->rows);
    }
}

function dbquery($sql)
{
    $GLOBALS['queries'][] = $sql;
    if (!preg_match("/^SELECT username FROM users WHERE username = '((?:[^'\\\\]|\\\\.)*)'$/", $sql, $match)) {
        throw new RuntimeException('Unexpected query: ' . $sql);
    }
    $typed = strtolower(str_replace("\xe2\x80\x8b", '', stripslashes($match[1])));
    $rows = [];
    foreach (['admin', "o'brien@example.com"] as $stored) {
        if ($stored === $typed) {
            $rows[] = ['username' => $stored];
        }
    }

    return new LoginThrottleUsersStubResult($rows);
}
