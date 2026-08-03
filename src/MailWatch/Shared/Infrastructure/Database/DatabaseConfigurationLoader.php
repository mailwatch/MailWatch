<?php

declare(strict_types=1);

namespace MailWatch\Shared\Infrastructure\Database;

use MailWatch\Shared\Infrastructure\Configuration\InvalidConfiguration;

/**
 * Builds the connection parameters from the constants conf.php already
 * defines, so that an installation needs no new configuration to be migrated.
 */
final class DatabaseConfigurationLoader
{
    /**
     * The engines this release offers an administrator, which is narrower than
     * what the code can connect to. SQLite and PostgreSQL arrive in 2.1, each
     * once its contract suite runs in CI.
     *
     * Rejecting them here rather than letting them through is the whole point:
     * the migrations run happily on SQLite, so an installation configured for
     * it would create its schema, report success, and then fail on the first
     * page, because the unmigrated page scripts still speak mysqli. A setup
     * that half-works is worse than one that refuses.
     *
     * @var list<string>
     */
    private const ENGINES = ['mysql', 'mariadb'];

    public function load(): DatabaseConfiguration
    {
        $engine = strtolower($this->string('DB_TYPE', 'mysql'));
        if (!\in_array($engine, self::ENGINES, true)) {
            throw new InvalidConfiguration(sprintf(
                'DB_TYPE "%s" is not available in this release; supported engines are %s',
                $engine,
                implode(' and ', self::ENGINES)
            ));
        }

        // DB_DSN is deliberately not read. conf.php.example composes it by
        // concatenation, so a password containing @ : / or # produces a string
        // that parses into the wrong host or credentials rather than failing.
        // The parts are encoded here instead.
        return DatabaseConfiguration::fromDsn(sprintf(
            '%s://%s:%s@%s:%d/%s',
            $engine,
            rawurlencode($this->required('DB_USER')),
            rawurlencode($this->string('DB_PASS', '')),
            rawurlencode($this->string('DB_HOST', 'localhost')),
            $this->port(),
            rawurlencode($this->required('DB_NAME'))
        ));
    }

    private function required(string $name): string
    {
        $value = $this->string($name, '');
        if ('' === $value) {
            throw new InvalidConfiguration("{$name} must be defined as a non-empty string");
        }

        return $value;
    }

    private function string(string $name, string $default): string
    {
        if (!\defined($name)) {
            return $default;
        }

        $value = \constant($name);
        if (!\is_string($value)) {
            throw new InvalidConfiguration("{$name} must be a string");
        }

        return $value;
    }

    private function port(): int
    {
        if (!\defined('DB_PORT')) {
            return 3306;
        }

        $value = \constant('DB_PORT');
        if (!\is_int($value) && !(\is_string($value) && ctype_digit($value))) {
            throw new InvalidConfiguration('DB_PORT must be a positive integer');
        }

        $port = (int)$value;
        if ($port < 1 || $port > 65535) {
            throw new InvalidConfiguration('DB_PORT must be a valid port number');
        }

        return $port;
    }
}
