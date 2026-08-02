<?php

declare(strict_types=1);

namespace App\Tests\Unit\Routing;

use MailWatch\Routing\PageRouteRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PageRouteRegistryTest extends TestCase
{
    public function testItMapsTheHomePageAndAnExistingPage(): void
    {
        $registry = new PageRouteRegistry();

        self::assertSame('index.php', $registry->pageForPath('/'));
        self::assertSame('index.php', $registry->pageForPath('/index.php'));
        self::assertSame('rep_message_listing.php', $registry->pageForPath('/rep_message_listing.php'));
        self::assertSame('do_message_ops.php', $registry->pageForPath('/do_message_ops.php'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function inaccessiblePaths(): iterable
    {
        yield 'configuration' => ['/conf.php'];
        yield 'database implementation' => ['/Database.php'];
        yield 'functions' => ['/functions.php'];
        yield 'include' => ['/filter.inc.php'];
        yield 'bootstrap' => ['/bootstrap.php'];
        yield 'API handler' => ['/api/logmail.php'];
        yield 'path traversal' => ['/../mailscanner/conf.php'];
        yield 'nested page' => ['/nested/login.php'];
        yield 'unknown page' => ['/unknown.php'];
    }

    #[DataProvider('inaccessiblePaths')]
    public function testItDoesNotExposeInternalOrUnknownFiles(string $path): void
    {
        self::assertNull((new PageRouteRegistry())->pageForPath($path));
    }
}
