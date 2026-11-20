<?php

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

class SessionChangeCheckTest extends TestCase
{
    public function testThePagesWithoutHtmlStartCheckTheSessionBeforeAnyAction()
    {
        foreach (['quarantine_action.php', 'viewpart.php'] as $page) {
            $source = file_get_contents(__DIR__ . '/../../mailscanner/' . $page);
            $login = strpos($source, "require __DIR__ . '/login.function.php';");
            $check = strpos($source, 'logoutOnSessionChange();');

            $this->assertNotFalse($login, $page);
            $this->assertNotFalse($check, $page);
            $this->assertGreaterThan($login, $check, $page);
            $this->assertStringNotContainsString('$_GET', substr($source, 0, $check), $page);
        }
    }

    public function testHtmlStartRunsTheSameCheck()
    {
        $source = file_get_contents(__DIR__ . '/../../mailscanner/functions.php');
        $htmlStart = substr($source, strpos($source, 'function html_start('), 4000);

        $this->assertStringContainsString('logoutOnSessionChange();', $htmlStart);
        $this->assertMatchesRegularExpression('/function logoutOnSessionChange\(\)\s*\{\s*if \(true === checkPrivilegeChange\(\$_SESSION\[\'myusername\'\]\) \|\| true === checkLoginExpiry\(\$_SESSION\[\'myusername\'\]\)\)/', $source);
    }
}
