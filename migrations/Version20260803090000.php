<?php

declare(strict_types=1);

namespace MailWatch\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * The maillog table, as the migrator's baseline for it.
 *
 * This reproduces what create.sql already installs rather than correcting it:
 * the ingestion gateway lands on top of this table, and a schema change made
 * in the same step would make a broken insert and a wrong column type
 * indistinguishable. The canonical types of the programme arrive in their own
 * migration, with contract tests.
 *
 * Existing installations never run this. They reach the same point through
 * upgrade.php, which already converges a 1.2.x database to the 1.3 schema, and
 * then declare it reached with `migrations:version --add`. From there both
 * paths share one linear history, and upgrade.php stops growing: everything
 * after this migration belongs to the migrator.
 *
 * One column type differs from create.sql on purpose. It declares timestamp and
 * last_update as MySQL TIMESTAMP, which is a 4-byte epoch capped at 2038, is
 * converted per session time zone on MySQL but not on PostgreSQL, and has no
 * DBAL type that emits it. Both become DATETIME here, holding UTC.
 *
 * That difference cannot be reconciled by a later declarative migration: DBAL
 * introspects MySQL TIMESTAMP as its own datetime type, so a schema comparison
 * reports no difference between the two and would emit no SQL. Converting an
 * upgraded installation therefore needs explicit SQL, guarded by the column
 * type read from information_schema.
 *
 * Three further things in create.sql have no portable expression and are not
 * reproduced here. None is read by application code:
 *
 *   - ON UPDATE CURRENT_TIMESTAMP on last_update. The column keeps its default,
 *     so rows still get a value on insert; nothing refreshes it on update until
 *     a gateway writes it explicitly.
 *   - the FULLTEXT index on subject, which create.sql already guards behind a
 *     MySQL version comment.
 *   - the per-column utf8mb4_unicode_520_ci collation, which is a comparison
 *     strategy per platform rather than part of the schema.
 *
 * The prefix lengths on the text indexes are declared and need no branch: DBAL
 * emits them on MySQL and indexes the whole column on engines that cannot.
 */
final class Version20260803090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the maillog table, the baseline for message ingestion';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('maillog');

        $table->addColumn('maillog_id', Types::BIGINT, ['autoincrement' => true, 'unsigned' => true]);
        $table->addColumn('timestamp', Types::DATETIME_MUTABLE, ['notnull' => false]);
        $table->addColumn('size', Types::BIGINT, ['notnull' => false, 'default' => 0]);
        $table->addColumn('sascore', Types::DECIMAL, ['precision' => 7, 'scale' => 2, 'notnull' => false, 'default' => '0.00']);
        $table->addColumn('mcpsascore', Types::DECIMAL, ['precision' => 7, 'scale' => 2, 'notnull' => false, 'default' => '0.00']);
        $table->addColumn('date', Types::DATE_MUTABLE, ['notnull' => false]);
        $table->addColumn('time', Types::TIME_MUTABLE, ['notnull' => false]);

        foreach ([
            'id', 'from_address', 'from_domain', 'to_address', 'to_domain', 'subject', 'clientip',
            'archive', 'spamreport', 'report', 'mcpreport', 'hostname', 'headers', 'messageid',
            'rblspamreport',
        ] as $column) {
            $table->addColumn($column, Types::TEXT, ['length' => 16777215, 'notnull' => false]);
        }

        // nameinfected counts infections; the others are flags, and DBAL emits
        // the TINYINT(1) that create.sql declares for them.
        $table->addColumn('nameinfected', Types::SMALLINT, ['notnull' => false, 'default' => 0]);
        foreach ([
            'isspam', 'ishighspam', 'issaspam', 'isrblspam', 'isfp', 'isfn',
            'spamallowlisted', 'spamblocklisted', 'virusinfected', 'otherinfected',
            'ismcp', 'ishighmcp', 'issamcp', 'mcpallowlisted', 'mcpblocklisted',
            'quarantined', 'released', 'salearn',
        ] as $column) {
            $table->addColumn($column, Types::BOOLEAN, ['notnull' => false, 'default' => false]);
        }

        $table->addColumn('token', Types::STRING, ['length' => 64, 'fixed' => true, 'notnull' => false]);
        $table->addColumn('ingestion_id', Types::STRING, ['length' => 64, 'fixed' => true, 'notnull' => false]);
        $table->addColumn('last_update', Types::DATETIME_MUTABLE, ['default' => 'CURRENT_TIMESTAMP']);

        $table->setPrimaryKey(['maillog_id']);

        // The ingestion key is what makes a replayed message a duplicate rather
        // than a second row, so the gateway depends on this being unique.
        $table->addUniqueIndex(['ingestion_id'], 'maillog_ingestion_id_uniq');

        $table->addIndex(['date', 'time'], 'maillog_datetime_idx');
        $table->addIndex(['quarantined'], 'maillog_quarantined');
        $table->addIndex(['timestamp'], 'timestamp_idx');
        $table->addIndex(['id'], 'maillog_id_idx', [], ['lengths' => [20]]);
        $table->addIndex(['clientip'], 'maillog_clientip_idx', [], ['lengths' => [20]]);
        $table->addIndex(['from_address'], 'maillog_from_idx', [], ['lengths' => [191]]);
        $table->addIndex(['to_address'], 'maillog_to_idx', [], ['lengths' => [191]]);
        $table->addIndex(['hostname'], 'maillog_host', [], ['lengths' => [30]]);
        $table->addIndex(['from_domain'], 'from_domain_idx', [], ['lengths' => [50]]);
        $table->addIndex(['to_domain'], 'to_domain_idx', [], ['lengths' => [50]]);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('maillog');
    }
}
