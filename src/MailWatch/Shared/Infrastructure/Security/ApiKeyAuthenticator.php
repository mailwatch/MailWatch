<?php

declare(strict_types=1);

namespace MailWatch\Shared\Infrastructure\Security;

use MailWatch\Shared\Infrastructure\Configuration\ApiConfiguration;

final readonly class ApiKeyAuthenticator
{
    public function __construct(private ApiConfiguration $configuration)
    {
    }

    public function isAuthorized(mixed $receivedKey): bool
    {
        return is_string($receivedKey)
            && hash_equals($this->configuration->apiKey(), $receivedKey);
    }
}
