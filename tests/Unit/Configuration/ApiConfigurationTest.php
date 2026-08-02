<?php

declare(strict_types=1);

namespace App\Tests\Unit\Configuration;

use MailWatch\ApplicationFactory;
use MailWatch\Configuration\ApiConfiguration;
use MailWatch\Configuration\InvalidConfiguration;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class ApiConfigurationTest extends TestCase
{
    public function testItExposesValidatedApiSettings(): void
    {
        $configuration = new ApiConfiguration('private-api-key', 4096, 8192);

        self::assertSame('private-api-key', $configuration->apiKey());
        self::assertSame(4096, $configuration->maxPayloadBytes());
        self::assertSame(8192, $configuration->maxSnapshotBytes());
    }

    public function testItRejectsAnEmptyApiKey(): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage('API_KEY must be a non-empty string');

        new ApiConfiguration('   ');
    }

    public function testItRejectsInvalidLimits(): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage('API_MAX_PAYLOAD_BYTES must be greater than zero');

        new ApiConfiguration('private-api-key', 0, 8192);
    }

    public function testTheApplicationFactoryReturnsItsConfiguration(): void
    {
        $configuration = new ApiConfiguration('private-api-key');
        $applicationFactory = new ApplicationFactory($configuration);

        self::assertSame($configuration, $applicationFactory->apiConfiguration());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testItLoadsTheApplicationConstants(): void
    {
        define('API_KEY', 'application-api-key');
        define('API_MAX_PAYLOAD_BYTES', '4096');
        define('API_MAX_SNAPSHOT_BYTES', 8192);

        $configuration = ApplicationFactory::create()->apiConfiguration();

        self::assertSame('application-api-key', $configuration->apiKey());
        self::assertSame(4096, $configuration->maxPayloadBytes());
        self::assertSame(8192, $configuration->maxSnapshotBytes());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testItRejectsAMissingApiKey(): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage('API_KEY must be defined as a string');

        ApplicationFactory::create();
    }
}
