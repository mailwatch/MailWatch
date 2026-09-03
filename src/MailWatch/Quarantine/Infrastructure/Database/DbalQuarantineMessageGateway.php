<?php

declare(strict_types=1);

namespace MailWatch\Quarantine\Infrastructure\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use MailWatch\Quarantine\Application\QuarantineMessageGateway;
use MailWatch\Quarantine\Domain\QuarantinedMessage;
use MailWatch\Shared\Domain\MessageScope;
use MailWatch\Shared\Infrastructure\Database\MessageScopePredicate;

final readonly class DbalQuarantineMessageGateway implements QuarantineMessageGateway
{
    private const SELECT = 'SELECT id, hostname, date, to_address, isspam, nameinfected, virusinfected, otherinfected'
        . ' FROM maillog WHERE id = :id';

    public function __construct(private Connection $connection)
    {
    }

    public function messageInScope(string $messageId, MessageScope $scope): ?QuarantinedMessage
    {
        [$predicate, $parameters] = MessageScopePredicate::build($scope);

        $row = $this->connection->fetchAssociative(
            self::SELECT . ' AND ' . $predicate,
            ['id' => $messageId, ...$parameters],
        );

        if (false === $row) {
            return null;
        }

        return new QuarantinedMessage(
            (string)($row['id'] ?? ''),
            (string)($row['hostname'] ?? ''),
            self::storageDate($row['date'] ?? null),
            (string)($row['to_address'] ?? ''),
            0 < (int)($row['isspam'] ?? 0),
            0 < (int)($row['nameinfected'] ?? 0)
                || 0 < (int)($row['virusinfected'] ?? 0)
                || 0 < (int)($row['otherinfected'] ?? 0),
        );
    }

    public function markReleased(string $messageId): void
    {
        $this->connection->update(
            'maillog',
            ['released' => true],
            ['id' => $messageId],
            ['released' => Types::BOOLEAN],
        );
    }

    public function recordLearningVerdict(string $messageId, bool $falsePositive, bool $falseNegative): void
    {
        $this->connection->update(
            'maillog',
            ['isfp' => $falsePositive, 'isfn' => $falseNegative],
            ['id' => $messageId],
            ['isfp' => Types::BOOLEAN, 'isfn' => Types::BOOLEAN],
        );
    }

    public function recordLearnedClass(string $messageId, int $class): void
    {
        // Bound as an integer on purpose: the column is declared boolean but
        // holds 1 for ham and 2 for spam, and the boolean type would convert
        // the two into the same value.
        $this->connection->update(
            'maillog',
            ['salearn' => $class],
            ['id' => $messageId],
            ['salearn' => ParameterType::INTEGER],
        );
    }

    public function clearQuarantineLocation(string $messageId): void
    {
        $this->connection->update('maillog', ['quarantined' => null], ['id' => $messageId]);
    }

    /**
     * The quarantine directory is named after the processing date, which the
     * legacy query formatted in SQL. Points in time are formatted in PHP now,
     * and a row without a date simply has no directory.
     */
    private static function storageDate(mixed $date): string
    {
        if (!\is_string($date) || '' === $date) {
            return '';
        }

        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', substr($date, 0, 10));

        return false === $parsed ? '' : $parsed->format('Ymd');
    }
}
