<?php

declare(strict_types=1);

namespace MailWatch\Shared\Http;

/**
 * What a request path resolves to.
 *
 * The handler is an application-level name, never a filename, except for
 * RouteType::Page where the page script is exactly what is being addressed.
 */
final readonly class Route
{
    /**
     * @param array<string, string> $parameters values taken from the path
     */
    public function __construct(
        public RouteType $type,
        public string $handler,
        public array $parameters = []
    ) {
    }

    public function parameter(string $name): ?string
    {
        return $this->parameters[$name] ?? null;
    }
}
