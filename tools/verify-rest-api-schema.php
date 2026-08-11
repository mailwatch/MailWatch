#!/usr/bin/env php
<?php

declare(strict_types=1);

use MailWatch\ApplicationFactory;
use MailWatch\Shared\Infrastructure\Database\RestApiSchemaVerifier;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This command can only run from the command line.\n");
    exit(2);
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

$configuration = dirname(__DIR__) . '/mailscanner/conf.php';
if (!is_file($configuration)) {
    fwrite(STDERR, "mailscanner/conf.php was not found; configure the installation first.\n");
    exit(2);
}

require_once $configuration;

$connection = null;
try {
    $connection = ApplicationFactory::databaseConnection();
    $errors = (new RestApiSchemaVerifier())->verify($connection->createSchemaManager()->introspectSchema());
} catch (Throwable $exception) {
    fwrite(STDERR, 'Unable to verify the REST API schema: ' . $exception::class . "\n");
    exit(2);
} finally {
    $connection?->close();
}

if ([] !== $errors) {
    fwrite(STDERR, "REST API schema verification failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, " - {$error}\n");
    }
    exit(1);
}

fwrite(STDOUT, "REST API supporting schema verified: allowlist, blocklist, user_filters, users.\n");
