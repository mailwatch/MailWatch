<?php

declare(strict_types=1);

/*
 * Entry point for vendor/bin/doctrine-migrations, which looks for this file in
 * the working directory or in config/.
 *
 * It loads conf.php for the credentials, the same file the web entry point
 * reads, so that migrating an installation needs no second configuration.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

$configuration = dirname(__DIR__) . '/mailscanner/conf.php';
if (!is_file($configuration)) {
    fwrite(STDERR, "mailscanner/conf.php was not found; copy conf.php.example and configure it first.\n");
    exit(1);
}

require_once $configuration;

return MailWatch\ApplicationFactory::migrations(
    MailWatch\ApplicationFactory::databaseConnection()
);
