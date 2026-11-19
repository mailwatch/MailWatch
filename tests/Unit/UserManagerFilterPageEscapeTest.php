<?php

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

class UserManagerFilterPageEscapeTest extends TestCase
{
    public function testTheFilterPageEscapesTheUsernameAndTheFilters()
    {
        $source = file_get_contents(__DIR__ . '/../../mailscanner/user_manager.php');

        // a username may hold < and >: the page of its filters printed it as markup
        $this->assertStringContainsString("__('userfilter12') . ' ' . htmlspecialchars(\$user->username,", $source);
        $this->assertStringContainsString("' <TR><TD>' . htmlspecialchars(\$row->filter,", $source);
        $this->assertSame(0, preg_match('/\' \. \$(user->username|row->filter) \. \'</', $source));
    }
}
