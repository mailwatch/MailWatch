<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Http;

use MailWatch\Shared\Http\RouteRegistry;
use MailWatch\Shared\Http\RouteType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RouteRegistryTest extends TestCase
{
    public function testItMapsTheHomePageAndAnExistingPage(): void
    {
        $registry = new RouteRegistry();

        foreach (['/' => 'index.php', '/index.php' => 'index.php', '/do_message_ops.php' => 'do_message_ops.php'] as $path => $page) {
            $route = $registry->match($path);

            self::assertNotNull($route, $path);
            self::assertSame(RouteType::Page, $route->type);
            self::assertSame($page, $route->handler);
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function apiPaths(): iterable
    {
        yield 'messages' => ['/api/messages', 'messages'];
        yield 'allow/block list' => ['/api/allow-block-list', 'allow-block-list'];
        yield 'spam settings' => ['/api/spam-settings', 'spam-settings'];
    }

    #[DataProvider('apiPaths')]
    public function testItMapsTheApiEndpoints(string $path, string $handler): void
    {
        $route = (new RouteRegistry())->match($path);

        self::assertNotNull($route);
        self::assertSame(RouteType::Api, $route->type);
        self::assertSame($handler, $route->handler);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function antivirusPaths(): iterable
    {
        yield 'clamav' => ['/status/antivirus/clamav', 'clamav'];
        yield 'sophos' => ['/status/antivirus/sophos', 'sophos'];
        yield 'mcafee' => ['/status/antivirus/mcafee', 'mcafee'];
        yield 'f-prot' => ['/status/antivirus/f-prot', 'f-prot'];
        yield 'f-secure' => ['/status/antivirus/f-secure', 'f-secure'];
        yield 'f-secure 12' => ['/status/antivirus/f-secure-12', 'f-secure12'];
    }

    #[DataProvider('antivirusPaths')]
    public function testItMapsTheAntivirusStatusPagesToAController(string $path, string $scanner): void
    {
        $route = (new RouteRegistry())->match($path);

        self::assertNotNull($route);
        self::assertSame(RouteType::Controller, $route->type);
        self::assertSame('antivirus-status', $route->handler);
        self::assertSame($scanner, $route->parameter('scanner'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function movedPaths(): iterable
    {
        yield 'clamav' => ['/clamav_status.php', '/status/antivirus/clamav'];
        yield 'f-secure 12' => ['/f-secure12_status.php', '/status/antivirus/f-secure-12'];
    }

    #[DataProvider('movedPaths')]
    public function testItRedirectsThePathsThesePagesUsedToHave(string $path, string $target): void
    {
        $route = (new RouteRegistry())->match($path);

        self::assertNotNull($route);
        self::assertSame(RouteType::Redirect, $route->type);
        self::assertSame($target, $route->handler);
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
        yield 'old flat controller path' => ['/clamav-status'];
        yield 'unknown scanner, nested' => ['/status/antivirus/norton'];
        yield 'controller path without scanner' => ['/status/antivirus'];
    }

    #[DataProvider('inaccessiblePaths')]
    public function testItDoesNotExposeInternalOrUnknownPaths(string $path): void
    {
        self::assertNull((new RouteRegistry())->match($path));
    }

    /**
     * The extracted pages must not remain reachable as page scripts as well, or
     * the old code would still be served under its old path.
     */
    public function testTheExtractedPagesAreNoLongerPageScripts(): void
    {
        $registry = new RouteRegistry();

        foreach (['/clamav_status.php', '/sophos_status.php', '/f-secure12_status.php'] as $path) {
            $route = $registry->match($path);

            self::assertNotNull($route);
            self::assertNotSame(RouteType::Page, $route->type, $path);
        }
    }
}
