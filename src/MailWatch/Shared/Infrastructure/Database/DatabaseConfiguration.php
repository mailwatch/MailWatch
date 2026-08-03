<?php

declare(strict_types=1);

namespace MailWatch\Shared\Infrastructure\Database;

use Doctrine\DBAL\Tools\DsnParser;
use MailWatch\Shared\Infrastructure\Configuration\InvalidConfiguration;

/**
 * Connection parameters, expressed as a DSN so that the engine is chosen by
 * configuration rather than assumed by the code that connects.
 */
final readonly class DatabaseConfiguration
{
    /**
     * What the code can connect to, which is not the same question as what an
     * administrator may select: SQLite is here because the tests and the
     * development workflow run on it, and it is deliberately absent from
     * DatabaseConfigurationLoader::ENGINES until 2.1 offers it in production.
     *
     * PostgreSQL is in neither list until its contract suite is green. A scheme
     * that parses is not a supported engine.
     *
     * @var array<string, string>
     */
    private const SCHEMES = [
        'mysql' => 'pdo_mysql',
        'mariadb' => 'pdo_mysql',
        'sqlite' => 'pdo_sqlite',
    ];

    /**
     * @param array<string, mixed> $parameters
     */
    private function __construct(
        private array $parameters
    ) {
    }

    public static function fromDsn(string $dsn): self
    {
        try {
            $parameters = (new DsnParser(self::SCHEMES))->parse($dsn);
        } catch (\Throwable $exception) {
            // The DSN carries the password, so nothing from it reaches the message.
            throw new InvalidConfiguration('The database DSN could not be parsed', 0, $exception);
        }

        // Checked on the parsed driver rather than on the raw scheme: the SQLite
        // forms are rewritten during parsing, and parse_url does not read them.
        $driver = $parameters['driver'] ?? null;
        if (!\in_array($driver, self::SCHEMES, true)) {
            throw new InvalidConfiguration(sprintf(
                'Unsupported database engine; this release supports %s',
                implode(', ', array_keys(self::SCHEMES))
            ));
        }

        return new self($parameters);
    }

    /**
     * @return array<string, mixed>
     */
    public function parameters(): array
    {
        return $this->parameters;
    }
}
