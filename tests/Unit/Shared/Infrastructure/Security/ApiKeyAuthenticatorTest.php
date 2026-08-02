<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Security;

use MailWatch\Shared\Infrastructure\Configuration\ApiConfiguration;
use MailWatch\Shared\Infrastructure\Security\ApiKeyAuthenticator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ApiKeyAuthenticatorTest extends TestCase
{
    public function testItAcceptsTheConfiguredApiKey(): void
    {
        $authenticator = new ApiKeyAuthenticator(new ApiConfiguration('private-api-key'));

        self::assertTrue($authenticator->isAuthorized('private-api-key'));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidKeys(): iterable
    {
        yield 'another string' => ['incorrect-api-key'];
        yield 'empty string' => [''];
        yield 'missing header' => [null];
        yield 'array' => [['private-api-key']];
        yield 'integer' => [1234];
    }

    #[DataProvider('invalidKeys')]
    public function testItRejectsAnyOtherValue(mixed $receivedKey): void
    {
        $authenticator = new ApiKeyAuthenticator(new ApiConfiguration('private-api-key'));

        self::assertFalse($authenticator->isAuthorized($receivedKey));
    }
}
