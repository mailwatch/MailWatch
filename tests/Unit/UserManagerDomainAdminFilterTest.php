<?php

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

class UserManagerDomainAdminFilterTest extends TestCase
{
    public function testOnlyTheActiveFiltersOfADomainAdministratorDelegateUserManagement()
    {
        $source = file_get_contents(__DIR__ . '/../../mailscanner/user_manager.php');
        $query = "\$sql = \"SELECT filter FROM user_filters WHERE username = '\" . safe_value(stripslashes(\$_SESSION['myusername'])) . \"'";

        // testSameDomainMembership() and the user list
        $this->assertSame(2, substr_count($source, $query . " AND active = 'Y'\";"));
        $this->assertSame(0, substr_count($source, $query . "\";"));
    }
}
