<?php

declare(strict_types=1);

namespace MailWatch\Shared\Http;

final readonly class ApiRequestContext
{
    private const REQUEST_ID_PATTERN = '/\A[A-Za-z0-9][A-Za-z0-9._-]{0,127}\z/D';

    private function __construct(private string $requestId)
    {
    }

    /**
     * @param array<string, mixed> $server
     */
    public static function fromServer(array $server): self
    {
        $receivedId = $server['HTTP_X_REQUEST_ID'] ?? null;
        if (is_string($receivedId) && 1 === preg_match(self::REQUEST_ID_PATTERN, $receivedId)) {
            return new self($receivedId);
        }

        return new self(bin2hex(random_bytes(16)));
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    /**
     * @return list<string>
     */
    public function responseHeaders(): array
    {
        return ['X-Request-ID: ' . $this->requestId];
    }
}
