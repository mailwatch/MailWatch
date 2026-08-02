<?php

declare(strict_types=1);

namespace MailWatch\Configuration;

final readonly class ApiConfiguration
{
    public function __construct(
        private string $apiKey,
        private int $maxPayloadBytes = 10 * 1024 * 1024,
        private int $maxSnapshotBytes = 5 * 1024 * 1024,
    ) {
        if ('' === trim($this->apiKey)) {
            throw new InvalidConfiguration('API_KEY must be a non-empty string');
        }
        if ($this->maxPayloadBytes < 1) {
            throw new InvalidConfiguration('API_MAX_PAYLOAD_BYTES must be greater than zero');
        }
        if ($this->maxSnapshotBytes < 1) {
            throw new InvalidConfiguration('API_MAX_SNAPSHOT_BYTES must be greater than zero');
        }
    }

    public function apiKey(): string
    {
        return $this->apiKey;
    }

    public function maxPayloadBytes(): int
    {
        return $this->maxPayloadBytes;
    }

    public function maxSnapshotBytes(): int
    {
        return $this->maxSnapshotBytes;
    }
}
