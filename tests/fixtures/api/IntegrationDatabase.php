<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use MailWatch\Migrations\Version20260803090000;
use Psr\Log\NullLogger;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

if (3 > $argc) {
    fwrite(STDERR, "Usage: IntegrationDatabase.php create|last <database> [fixture]\n");
    exit(2);
}

$command = $argv[1];
$connection = DriverManager::getConnection([
    'driver' => 'pdo_sqlite',
    'path' => $argv[2],
]);

try {
    if ('create' === $command) {
        createDatabase($connection, $argv[3] ?? '');
    } elseif ('last' === $command) {
        printLastMailLogEntry($connection);
    } else {
        throw new InvalidArgumentException("Unknown command: {$command}");
    }
} finally {
    $connection->close();
}

function createDatabase(Connection $connection, string $fixturePath): void
{
    if ('' === $fixturePath || !is_file($fixturePath)) {
        throw new InvalidArgumentException('The integration fixture does not exist');
    }

    $schema = new Schema();
    (new Version20260803090000($connection, new NullLogger()))->up($schema);
    $connection->createSchemaManager()->createSchemaObjects($schema);
    $connection->executeStatement('CREATE TABLE allowlist (to_address TEXT, from_address TEXT)');
    $connection->executeStatement('CREATE TABLE blocklist (to_address TEXT, from_address TEXT)');
    $connection->executeStatement('CREATE TABLE user_filters (username TEXT, filter TEXT, active TEXT)');
    $connection->executeStatement(
        'CREATE TABLE users (username TEXT, spamscore REAL, highspamscore REAL, noscan INTEGER)'
    );

    $fixture = json_decode((string)file_get_contents($fixturePath), true, 512, JSON_THROW_ON_ERROR);
    foreach (['allowlist', 'blocklist'] as $table) {
        foreach ($fixture[$table] as $entry) {
            $connection->insert($table, $entry);
        }
    }
    foreach ($fixture['user_filters'] as $filter) {
        $connection->insert('user_filters', $filter);
    }
    foreach ($fixture['users'] as $user) {
        $connection->insert('users', $user);
    }
}

function printLastMailLogEntry(Connection $connection): void
{
    $entry = $connection->fetchAssociative(
        'SELECT
            timestamp, id, size, from_address AS "from", from_domain,
            to_address AS "to", to_domain, subject, clientip, archive AS archiveplaces,
            isspam, ishighspam AS ishigh, issaspam, isrblspam, spamallowlisted,
            spamblocklisted, sascore, spamreport, virusinfected, nameinfected,
            otherinfected, report AS reports, ismcp, ishighmcp, issamcp,
            mcpallowlisted, mcpblocklisted, mcpsascore, mcpreport, hostname,
            date, time, headers, quarantined, rblspamreport, token, messageid,
            ingestion_id
         FROM maillog
         ORDER BY maillog_id DESC
         LIMIT 1'
    );

    if (false === $entry) {
        throw new RuntimeException('The integration database contains no mail log entry');
    }

    echo json_encode($entry, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
}
