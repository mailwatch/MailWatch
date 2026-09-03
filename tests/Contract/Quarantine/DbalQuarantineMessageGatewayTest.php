<?php

declare(strict_types=1);

namespace App\Tests\Contract\Quarantine;

use App\Tests\Support\ContractStore;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use MailWatch\Migrations\Version20260803090000;
use MailWatch\Shared\Domain\MessageScope;
use MailWatch\Quarantine\Infrastructure\Database\DbalQuarantineMessageGateway;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class DbalQuarantineMessageGatewayTest extends TestCase
{
    private Connection $connection;
    private DbalQuarantineMessageGateway $gateway;

    protected function setUp(): void
    {
        $this->connection = ContractStore::connection();
        ContractStore::dropTables($this->connection, ['maillog']);

        $schema = new Schema();
        (new Version20260803090000($this->connection, new NullLogger()))->up($schema);
        $this->connection->createSchemaManager()->createSchemaObjects($schema);
        $this->gateway = new DbalQuarantineMessageGateway($this->connection);
    }

    protected function tearDown(): void
    {
        ContractStore::dropTables($this->connection, ['maillog']);
        $this->connection->close();
    }

    public function testItReadsWhereTheMessageIsStoredAndHowItWasClassified(): void
    {
        $this->insertMessage([
            'id' => 'message-001',
            'hostname' => 'mail.example.test',
            'date' => '2026-08-11',
            'to_address' => 'owner@example.test',
            'isspam' => true,
            'virusinfected' => true,
        ]);

        $message = $this->gateway->messageInScope('message-001', MessageScope::unrestricted());

        self::assertNotNull($message);
        self::assertSame('message-001', $message->id);
        self::assertSame('mail.example.test', $message->hostname);
        self::assertSame('20260811', $message->storageDate);
        self::assertSame('owner@example.test', $message->recipients);
        self::assertTrue($message->spam);
        self::assertTrue($message->dangerous);
    }

    public function testABlockedFileCountsAsDangerousContent(): void
    {
        $this->insertMessage(['id' => 'message-002', 'nameinfected' => 2]);

        $message = $this->gateway->messageInScope('message-002', MessageScope::unrestricted());

        self::assertNotNull($message);
        self::assertTrue($message->dangerous);
        self::assertFalse($message->spam);
    }

    public function testARowWithoutADateHasNoStorageDirectory(): void
    {
        $this->insertMessage(['id' => 'message-003', 'date' => null]);

        $message = $this->gateway->messageInScope('message-003', MessageScope::unrestricted());

        self::assertNotNull($message);
        self::assertSame('', $message->storageDate);
    }

    public function testAnUnknownMessageIsNotFound(): void
    {
        self::assertNull($this->gateway->messageInScope('absent', MessageScope::unrestricted()));
    }

    public function testADeniedScopeReachesNoMessage(): void
    {
        $this->insertMessage(['id' => 'message-004']);

        self::assertNull($this->gateway->messageInScope('message-004', MessageScope::denied()));
    }

    public function testAUserReachesTheirOwnMailAndNobodyElses(): void
    {
        $this->insertMessage(['id' => 'mine', 'to_address' => 'owner@example.test']);
        $this->insertMessage(['id' => 'theirs', 'to_address' => 'someone@example.test']);

        $scope = MessageScope::forAccount('owner@example.test', 'U', [], false);

        self::assertNotNull($this->gateway->messageInScope('mine', $scope));
        self::assertNull($this->gateway->messageInScope('theirs', $scope));
    }

    /**
     * The recipient column holds every address of a multi-recipient message,
     * so membership of the list has to count, wherever in it the address sits.
     */
    public function testAUserReachesAMessageAddressedToSeveralRecipients(): void
    {
        $this->insertMessage(['id' => 'first', 'to_address' => 'owner@example.test,other@example.test']);
        $this->insertMessage(['id' => 'middle', 'to_address' => 'a@example.test,owner@example.test,b@example.test']);
        $this->insertMessage(['id' => 'last', 'to_address' => 'other@example.test,owner@example.test']);

        $scope = MessageScope::forAccount('owner@example.test', 'U', [], false);

        foreach (['first', 'middle', 'last'] as $id) {
            self::assertNotNull($this->gateway->messageInScope($id, $scope), $id);
        }
    }

    public function testAUserReachesWhatTheySentUnlessTheScopeIsRecipientOnly(): void
    {
        $this->insertMessage([
            'id' => 'sent',
            'to_address' => 'somebody@third.test',
            'from_address' => 'owner@example.test',
        ]);

        self::assertNotNull($this->gateway->messageInScope(
            'sent',
            MessageScope::forAccount('owner@example.test', 'U', [], false),
        ));
        self::assertNull($this->gateway->messageInScope(
            'sent',
            MessageScope::forAccount('owner@example.test', 'U', [], true),
        ));
    }

    public function testADomainAdministratorReachesTheirDomain(): void
    {
        $this->insertMessage(['id' => 'ours', 'to_address' => 'someone@example.test', 'to_domain' => 'example.test']);
        $this->insertMessage(['id' => 'theirs', 'to_address' => 'someone@other.test', 'to_domain' => 'other.test']);

        $scope = MessageScope::forAccount('admin@example.test', 'D', [], false);

        self::assertNotNull($this->gateway->messageInScope('ours', $scope));
        self::assertNull($this->gateway->messageInScope('theirs', $scope));
    }

    public function testTheScopeIgnoresTheCaseTheAddressWasStoredIn(): void
    {
        $this->insertMessage(['id' => 'shouty', 'to_address' => 'Owner@Example.Test']);

        self::assertNotNull($this->gateway->messageInScope(
            'shouty',
            MessageScope::forAccount('owner@example.test', 'U', [], false),
        ));
    }

    public function testItMarksAMessageReleased(): void
    {
        $this->insertMessage(['id' => 'message-005']);

        $this->gateway->markReleased('message-005');

        self::assertSame(1, (int)$this->column('message-005', 'released'));
    }

    public function testItRecordsTheLearningVerdictWithoutLosingAFalseValue(): void
    {
        $this->insertMessage(['id' => 'message-006', 'isfp' => true, 'isfn' => true]);

        $this->gateway->recordLearningVerdict('message-006', false, false);

        self::assertSame(0, (int)$this->column('message-006', 'isfp'));
        self::assertSame(0, (int)$this->column('message-006', 'isfn'));
    }

    /**
     * The column is declared boolean but distinguishes ham from spam, so the
     * 2 has to survive the write.
     */
    public function testItRecordsSpamAndHamAsDifferentLearnedClasses(): void
    {
        $this->insertMessage(['id' => 'ham']);
        $this->insertMessage(['id' => 'spam']);

        $this->gateway->recordLearnedClass('ham', 1);
        $this->gateway->recordLearnedClass('spam', 2);

        self::assertSame(1, (int)$this->column('ham', 'salearn'));
        self::assertSame(2, (int)$this->column('spam', 'salearn'));
    }

    public function testItForgetsWhereADeletedMessageWasStored(): void
    {
        $this->insertMessage(['id' => 'message-007', 'quarantined' => true]);

        $this->gateway->clearQuarantineLocation('message-007');

        self::assertNull($this->column('message-007', 'quarantined'));
    }

    /** @param array<string, mixed> $overrides */
    private function insertMessage(array $overrides = []): void
    {
        $defaults = [
            'id' => 'message-001',
            'hostname' => 'mail.example.test',
            'date' => '2026-08-11',
            'to_address' => 'owner@example.test',
            'to_domain' => 'example.test',
            'from_address' => 'sender@third.test',
            'from_domain' => 'third.test',
            'isspam' => false,
            'nameinfected' => 0,
            'virusinfected' => false,
            'otherinfected' => false,
            'isfp' => false,
            'isfn' => false,
            'salearn' => false,
            'released' => false,
            'quarantined' => false,
        ];

        $this->connection->insert('maillog', array_merge($defaults, $overrides), [
            'isspam' => Types::BOOLEAN,
            'virusinfected' => Types::BOOLEAN,
            'otherinfected' => Types::BOOLEAN,
            'isfp' => Types::BOOLEAN,
            'isfn' => Types::BOOLEAN,
            'salearn' => Types::BOOLEAN,
            'released' => Types::BOOLEAN,
            'quarantined' => Types::BOOLEAN,
        ]);
    }

    private function column(string $messageId, string $column): mixed
    {
        return $this->connection->fetchOne(
            "SELECT {$column} FROM maillog WHERE id = ?",
            [$messageId],
        );
    }
}
