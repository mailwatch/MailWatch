<?php

declare(strict_types=1);

namespace App\Tests\Unit\Quarantine\Infrastructure\Rpc;

use MailWatch\ApplicationFactory;
use PHPUnit\Framework\TestCase;

/**
 * The protocol adapter itself is edge code with no seam worth faking, like
 * the LDAP and IMAP verifiers. What is pinned here is the security default,
 * because it is the kind of setting that flips back by accident.
 */
final class XmlRpcQuarantineNodeTest extends TestCase
{
    public function testCertificateVerificationIsOnWhenTheInstallationSaysNothing(): void
    {
        self::assertFalse(
            \defined('RPC_VERIFY_PEER'),
            'This test describes what happens when conf.php does not set the constant.',
        );

        $node = ApplicationFactory::quarantineRemoteNode();

        self::assertTrue((new \ReflectionProperty($node, 'verifyPeer'))->getValue($node));
    }

    public function testTheExampleConfigurationShipsVerificationOnAndExplainsHowToTurnItOff(): void
    {
        $example = file_get_contents(\dirname(__DIR__, 5) . '/mailscanner/conf.php.example');
        self::assertIsString($example);

        self::assertStringContainsString("define('RPC_VERIFY_PEER', true);", $example);
        self::assertStringContainsString('self-signed certificates', $example);
    }

    /**
     * A conf.php constant missing from the analysis configuration is treated
     * as having the value the example file gives it.
     */
    public function testTheConstantIsDeclaredForStaticAnalysis(): void
    {
        $configuration = file_get_contents(\dirname(__DIR__, 5) . '/phpstan.dist.neon');
        self::assertIsString($configuration);

        self::assertStringContainsString('RPC_VERIFY_PEER: bool', $configuration);
    }
}
