<?php

declare(strict_types=1);

namespace App\Tests\Unit\Quarantine;

use PHPUnit\Framework\TestCase;

/**
 * What the quarantine operations may still do for themselves.
 *
 * Their data is gone: every maillog read and write goes through the gateway,
 * so the scope of a signed-in account is a bound parameter rather than a SQL
 * fragment carried in the session. Their storage is gone too: the quarantine
 * tree is read and pruned through a port. Mail delivery, the SpamAssassin
 * learner and XML-RPC dispatch are still theirs, and are the rest of this
 * boundary.
 */
final class LegacyQuarantineBoundaryTest extends TestCase
{
    private const OPERATIONS = [
        'quarantine_list' => ['function quarantine_list(', 'function quarantine_storage('],
        'quarantine_list_items' => ['function quarantine_list_items(', 'function quarantine_release('],
        'quarantine_release' => ['function quarantine_release(', 'function quarantine_learn('],
        'quarantine_learn' => ['function quarantine_learn(', 'function quarantine_delete('],
        'quarantine_delete' => ['function quarantine_delete(', 'function fixMessageId('],
    ];

    /** The operations that no longer reach the filesystem at all. */
    private const WITHOUT_STORAGE_ACCESS = ['quarantine_list', 'quarantine_list_items', 'quarantine_delete'];

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

    public function testTheOperationsDoNotOpenTheQuarantineThemselves(): void
    {
        foreach (self::WITHOUT_STORAGE_ACCESS as $name) {
            $body = self::functionSource(...self::OPERATIONS[$name]);

            foreach (['opendir' . '(', 'readdir' . '(', 'closedir' . '(', 'unlink' . '(', 'shell_exec' . '('] as $call) {
                self::assertStringNotContainsString($call, $body, $name);
            }
            self::assertStringContainsString('quarantine_storage()', $body, $name);
        }
    }

    public function testTheOperationsSpeakToAnotherNodeThroughOnePort(): void
    {
        foreach (self::OPERATIONS as $name => [$start, $end]) {
            $body = self::functionSource($start, $end);

            foreach (['xmlrpcval', 'xmlrpcmsg', 'xmlrpc_wrapper' . '(', 'php_xmlrpc_decode', 'faultCode'] as $call) {
                self::assertStringNotContainsString($call, $body, $name);
            }
        }

        foreach (['quarantine_list_items', 'quarantine_release', 'quarantine_learn', 'quarantine_delete'] as $name) {
            $body = self::functionSource(...self::OPERATIONS[$name]);

            self::assertStringContainsString('quarantineRemoteNode()', $body, $name);
            self::assertStringContainsString('RemoteQuarantineFailure', $body, $name);
        }
    }

    public function testReleasingGoesThroughTheReleaserAndCarriesNoTransportOfItsOwn(): void
    {
        $body = self::functionSource(...self::OPERATIONS['quarantine_release']);

        self::assertStringContainsString('quarantineReleaser(', $body);
        self::assertStringContainsString('$outcome->delivered', $body);
        foreach ([
            'Mail_smtp',
            'Mail_mime',
            'PEAR_Error',
            'require_once',
            'exec' . '(',
            'escapeshellarg' . '(',
            'QUARANTINE_USE_SENDMAIL',
            'QUARANTINE_SENDMAIL_PATH',
            'MAILWATCH_MAIL_HOST',
        ] as $legacy) {
            self::assertStringNotContainsString($legacy, $body);
        }
    }

    public function testLearningRunsThroughTheLearnerPortAndItsActions(): void
    {
        $body = self::functionSource(...self::OPERATIONS['quarantine_learn']);

        self::assertStringContainsString('quarantineLearner()', $body);
        self::assertStringContainsString('LearnAction::tryFrom(', $body);
        self::assertStringContainsString('LearningVerdict::of(', $body);
        self::assertStringNotContainsString('exec' . '(', $body);
        self::assertStringNotContainsString('SA_DIR', $body);
        self::assertStringNotContainsString('SA_PREFS', $body);
        self::assertStringNotContainsString('SA_MAXSIZE', $body);
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
