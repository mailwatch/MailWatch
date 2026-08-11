<?php

declare(strict_types=1);

namespace App\Tests\Unit\Quarantine;

use PHPUnit\Framework\TestCase;

final class LegacyQuarantineAuthorizationBoundaryTest extends TestCase
{
    public function testPopupMutationsRequirePostSessionAndFormTokens(): void
    {
        $source = self::legacySource('quarantine_action.php');

        self::assertStringContainsString("'POST' !== (\$_SERVER['REQUEST_METHOD']", $source);
        self::assertStringContainsString("checkToken(\$_POST['token']", $source);
        self::assertStringContainsString("checkFormToken('/quarantine_action.php form token'", $source);
        self::assertStringContainsString('->canRelease(', $source);
        self::assertStringNotContainsString('$_GET', $source);
    }

    public function testEveryDangerousContentEntryPointUsesTheCentralPolicy(): void
    {
        $viewMail = self::legacySource('viewmail.php');
        self::assertStringContainsString('->canView($dangerous)', $viewMail);
        self::assertStringContainsString('->canRelease($dangerous)', $viewMail);
        self::assertStringContainsString("generateFormToken('/quarantine_action.php form token')", $viewMail);

        $viewPart = self::legacySource('viewpart.php');
        self::assertStringContainsString('virusinfected, nameinfected, otherinfected', $viewPart);
        self::assertStringContainsString('->canView($dangerous)', $viewPart);

        $detail = self::legacySource('detail.php');
        self::assertStringContainsString('->canRelease($containsDangerousContent)', $detail);
        self::assertStringContainsString('->canUseAlternateRecipient($containsDangerousContent)', $detail);
        self::assertStringContainsString("->canView('Y' === \$item['dangerous'])", $detail);

        self::assertStringContainsString(
            '->canRelease($dangerous)',
            self::legacySource('do_message_ops.php'),
        );
    }

    private static function legacySource(string $file): string
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/mailscanner/' . $file);
        self::assertIsString($source);

        return $source;
    }
}
