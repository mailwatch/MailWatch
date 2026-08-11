<?php

declare(strict_types=1);

namespace App\Tests\Unit\Quarantine;

use PHPUnit\Framework\TestCase;

/**
 * The quarantine operations own effects — files, mail, the learner, XML-RPC —
 * but no longer own their data. Every maillog read and write goes through the
 * gateway, so that the scope of a signed-in account is a bound parameter
 * rather than a SQL fragment carried in the session.
 */
final class LegacyQuarantineSqlBoundaryTest extends TestCase
{
    private const OPERATIONS = [
        'quarantine_list_items' => ['function quarantine_list_items(', 'function quarantine_release('],
        'quarantine_release' => ['function quarantine_release(', 'function quarantine_learn('],
        'quarantine_learn' => ['function quarantine_learn(', 'function quarantine_delete('],
        'quarantine_delete' => ['function quarantine_delete(', 'function fixMessageId('],
    ];

    public function testTheQuarantineOperationsContainNoSql(): void
    {
        foreach (self::OPERATIONS as $name => [$start, $end]) {
            $body = self::functionSource($start, $end);

            self::assertStringNotContainsString('dbquery' . '(', $body, $name);
            self::assertStringNotContainsString('safe_value' . '(', $body, $name);
            self::assertStringNotContainsString('Database' . '::', $body, $name);
            self::assertStringNotContainsString('UPDATE ' . 'maillog', $body, $name);
            self::assertStringNotContainsString('FROM' . "\n  maillog", $body, $name);
            self::assertStringNotContainsString('global_' . 'filter', $body, $name);
        }
    }

    public function testTheLookupAuthorisesThroughTheMessageScope(): void
    {
        $body = self::functionSource(...self::OPERATIONS['quarantine_list_items']);

        self::assertStringContainsString('quarantineMessages()->messageInScope(', $body);
        self::assertStringContainsString('MessageScope::unrestricted()', $body);
    }

    public function testTheMutationsGoThroughTheGateway(): void
    {
        self::assertStringContainsString(
            'markReleased(',
            self::functionSource(...self::OPERATIONS['quarantine_release']),
        );
        self::assertStringContainsString(
            'recordLearningVerdict(',
            self::functionSource(...self::OPERATIONS['quarantine_learn']),
        );
        self::assertStringContainsString(
            'recordLearnedClass(',
            self::functionSource(...self::OPERATIONS['quarantine_learn']),
        );
        self::assertStringContainsString(
            'clearQuarantineLocation(',
            self::functionSource(...self::OPERATIONS['quarantine_delete']),
        );
    }

    public function testThePagesPassAScopeInsteadOfASessionSqlFragment(): void
    {
        foreach (['detail.php', 'do_message_ops.php', 'quarantine_action.php'] as $page) {
            $source = file_get_contents(dirname(__DIR__, 3) . '/mailscanner/' . $page);
            self::assertIsString($source);

            self::assertStringContainsString('quarantineMessageScope($_SESSION)', $source, $page);
            self::assertStringNotContainsString(
                "quarantine_list_items(\$id, RPC_ONLY, \$_SESSION['global_filter']",
                $source,
                $page,
            );
        }
    }

    private static function functionSource(string $startMarker, string $endMarker): string
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/mailscanner/functions.php');
        self::assertIsString($source);

        $start = strpos($source, $startMarker);
        self::assertIsInt($start);
        $end = strpos($source, $endMarker, $start);
        self::assertIsInt($end);

        return substr($source, $start, $end - $start);
    }
}
