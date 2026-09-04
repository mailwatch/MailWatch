<?php

declare(strict_types=1);

namespace MailWatch\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Move the existing mtalog rows from the server's wall-clock time to UTC.
 *
 * mtalog.timestamp has always been a DATETIME, so the conversion of the
 * TIMESTAMP columns (Version20260803120000) did not touch it, and MySQL never
 * converted it either: the MTA log processor wrote FROM_UNIXTIME() in the
 * session's zone, the server's default, and the readers formatted it back in
 * that same zone. Since 2.0 the processor writes UTC and every reader - the
 * filter, the reports, the message detail - treats the column as UTC, so a
 * row written before the upgrade would be shown with the server's offset
 * applied twice, and a re-read of the MTA log would not match it through
 * the unique index.
 *
 * The rows are shifted under the server's own zone: UNIX_TIMESTAMP() reads
 * the wall-clock value in @@global.time_zone, which is the zone that wrote
 * it, daylight saving included and without the time zone tables (a zone
 * named SYSTEM resolves through the operating system), and the epoch becomes
 * a UTC DATETIME by plain arithmetic. The hour that repeats when daylight
 * saving ends is ambiguous and is read as its first occurrence.
 *
 * The migration explicitly selects the zone rather than trusting the session:
 * an earlier migration in the same run pins the session to UTC, which would
 * turn this one into a no-op.
 *
 * This is one UPDATE over the table. On a large mtalog it takes a while and
 * holds row locks, so the MTA log processor should be stopped for the
 * duration. An installation that already ran the processor of an unreleased
 * 2.0 checkout holds UTC rows after that point and local rows before it,
 * which this migration cannot tell apart: check the recent rows against the
 * mail log afterwards and correct them by hand if the shift reached them.
 */
final class Version20260904090000 extends AbstractMigration
{
    private const RANGE = "timestamp > '1970-01-02 00:00:00' AND timestamp < '2038-01-01 00:00:00'";

    public function getDescription(): string
    {
        return 'Convert the existing mtalog rows from the server zone to UTC';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(
            !$this->platformIsMySQL(),
            'Only MySQL and MariaDB installations wrote mtalog in the server zone'
        );

        $this->addSql('SET time_zone = @@global.time_zone');
        $this->addSql(
            "UPDATE mtalog SET timestamp = DATE_ADD('1970-01-01 00:00:00', INTERVAL UNIX_TIMESTAMP(timestamp) SECOND)
             WHERE " . self::RANGE
        );
        $this->addSql("SET time_zone = '+00:00'");
    }

    public function down(Schema $schema): void
    {
        $this->skipIf(
            !$this->platformIsMySQL(),
            'Only MySQL and MariaDB installations wrote mtalog in the server zone'
        );

        $this->addSql('SET time_zone = @@global.time_zone');
        $this->addSql(
            "UPDATE mtalog SET timestamp = FROM_UNIXTIME(TIMESTAMPDIFF(SECOND, '1970-01-01 00:00:00', timestamp))
             WHERE " . self::RANGE
        );
        $this->addSql("SET time_zone = '+00:00'");
    }

    private function platformIsMySQL(): bool
    {
        return $this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform;
    }
}
