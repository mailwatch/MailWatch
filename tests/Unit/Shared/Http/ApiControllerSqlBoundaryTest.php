<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ApiControllerSqlBoundaryTest extends TestCase
{
    public function testTheApiEntryPointDoesNotLoadTheLegacyDatabaseClass(): void
    {
        $source = file_get_contents(dirname(__DIR__, 4) . '/public_html/index.php');
        self::assertIsString($source);
        self::assertStringNotContainsString('mailscanner/Database.php', $source);
    }

    #[DataProvider('controllerFiles')]
    public function testApiControllersContainNeitherSqlNorDatabaseDriverCalls(string $relativePath): void
    {
        $source = file_get_contents(dirname(__DIR__, 4) . '/' . $relativePath);
        self::assertIsString($source);

        self::assertDoesNotMatchRegularExpression(
            '/\b(?:INSERT\s+INTO|DELETE\s+FROM|UPDATE\s+\w+\s+SET|SELECT\s+.+\s+FROM)\b/is',
            $source
        );
        self::assertStringNotContainsString('mysqli', $source);
        self::assertStringNotContainsString('Doctrine\\DBAL', $source);
        self::assertStringNotContainsString('error_log(', $source, 'API controllers must use structured telemetry');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function controllerFiles(): iterable
    {
        yield 'messages' => ['src/MailWatch/MailLog/Http/MessageController.php'];
        yield 'allow/block list' => ['src/MailWatch/Lists/Http/AllowBlockListController.php'];
        yield 'spam settings' => ['src/MailWatch/SpamSettings/Http/SpamSettingsController.php'];
    }
}
