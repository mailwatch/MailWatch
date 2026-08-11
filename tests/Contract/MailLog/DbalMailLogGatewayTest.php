<?php

declare(strict_types=1);

namespace App\Tests\Contract\MailLog;

use App\Tests\Support\ContractStore;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use MailWatch\MailLog\Application\IngestionResult;
use MailWatch\MailLog\Domain\MailLogEntry;
use MailWatch\MailLog\Infrastructure\Database\DbalMailLogGateway;
use MailWatch\Migrations\Version20260803090000;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class DbalMailLogGatewayTest extends TestCase
{
    private Connection $connection;
    private DbalMailLogGateway $gateway;

    protected function setUp(): void
    {
        $this->connection = ContractStore::connection();
        ContractStore::dropTables($this->connection, ['maillog']);

        $schema = new Schema();
        (new Version20260803090000($this->connection, new NullLogger()))->up($schema);
        $this->connection->createSchemaManager()->createSchemaObjects($schema);
        $this->gateway = new DbalMailLogGateway($this->connection);
    }

    protected function tearDown(): void
    {
        ContractStore::dropTables($this->connection, ['maillog']);
        $this->connection->close();
    }

    public function testItStoresTheCompleteMailLogRecord(): void
    {
        $result = $this->gateway->store($this->entry(), 'an-ingestion-key');

        self::assertSame(IngestionResult::Inserted, $result);
        $stored = $this->connection->fetchAssociative('SELECT * FROM maillog');
        self::assertIsArray($stored);
        self::assertSame('2026-08-01 10:34:56', $stored['timestamp']);
        self::assertSame('message-001', $stored['id']);
        self::assertSame(2048, (int)$stored['size']);
        self::assertSame('sender@example.test', $stored['from_address']);
        self::assertSame('recipient@example.invalid', $stored['to_address']);
        self::assertSame(1, (int)$stored['isspam']);
        self::assertSame(0, (int)$stored['ishighspam']);
        self::assertSame(6.5, (float)$stored['sascore']);
        self::assertSame('2026-08-01', $stored['date']);
        self::assertSame('12:34:56', $stored['time']);
        self::assertSame('an-ingestion-key', $stored['ingestion_id']);
    }

    public function testItReportsAnIdempotentReplayWithoutASecondRow(): void
    {
        $entry = $this->entry();

        self::assertSame(IngestionResult::Inserted, $this->gateway->store($entry, 'same-key'));
        self::assertSame(IngestionResult::Duplicate, $this->gateway->store($entry, 'same-key'));
        self::assertSame(1, (int)$this->connection->fetchOne('SELECT COUNT(*) FROM maillog'));
    }

    public function testMessagesWithoutAnIdempotencyKeyRemainBackwardCompatible(): void
    {
        $entry = $this->entry();

        self::assertSame(IngestionResult::Inserted, $this->gateway->store($entry, null));
        self::assertSame(IngestionResult::Inserted, $this->gateway->store($entry, null));
        self::assertSame(2, (int)$this->connection->fetchOne('SELECT COUNT(*) FROM maillog'));
    }

    private function entry(): MailLogEntry
    {
        return new MailLogEntry([
            'timestamp' => '2026-08-01T12:34:56+02:00',
            'id' => 'message-001',
            'size' => 2048,
            'from' => 'sender@example.test',
            'from_domain' => 'example.test',
            'to' => 'recipient@example.invalid',
            'to_domain' => 'example.invalid',
            'subject' => 'Gateway contract',
            'clientip' => '192.0.2.10',
            'archiveplaces' => '',
            'isspam' => 1,
            'ishigh' => 0,
            'issaspam' => 1,
            'isrblspam' => 0,
            'spamallowlisted' => 1,
            'spamblocklisted' => 0,
            'sascore' => 6.5,
            'spamreport' => 'TEST_RULE',
            'virusinfected' => 0,
            'nameinfected' => 0,
            'otherinfected' => 0,
            'reports' => '',
            'ismcp' => 0,
            'ishighmcp' => 0,
            'issamcp' => 0,
            'mcpallowlisted' => 0,
            'mcpblocklisted' => 1,
            'mcpsascore' => 0,
            'mcpreport' => '',
            'hostname' => 'mx.example.test',
            'headers' => 'Message-ID: <message-001@example.test>',
            'quarantined' => 0,
            'rblspamreport' => '',
            'token' => '0000000000000000000000000000000000000000',
            'messageid' => '<message-001@example.test>',
        ]);
    }
}
