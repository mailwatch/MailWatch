<?php

namespace App\Tests;

use PHPUnit\Framework\TestCase;

class LdapAuthenticateTest extends TestCase
{
    public function testTheUsernameIsEscapedForTheSearchFilter()
    {
        $source = file_get_contents(__DIR__ . '/../mailscanner/functions.php');
        $start = strpos($source, 'function ldap_authenticate(');
        $function = substr($source, $start, strpos($source, 'function ldap_print_error(') - $start);

        // the DN escaping leaves * ( ) alone, so 'jdo*' matched the entry of jdoe@example.com in the filter
        $this->assertStringContainsString("ldap_escape(strtolower(\$username), '', LDAP_ESCAPE_FILTER)", $function);
        $this->assertStringNotContainsString('LDAP_ESCAPE_DN', $function);
        $this->assertStringContainsString('sprintf(LDAP_FILTER, $username)', $function);
    }
}
