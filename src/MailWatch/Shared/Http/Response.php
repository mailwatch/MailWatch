<?php

declare(strict_types=1);

namespace MailWatch\Shared\Http;

/**
 * What a web controller returns.
 *
 * Controllers build a response and the entry point sends it, so that what a
 * controller decided can be asserted in a test without capturing output or
 * sending headers.
 */
final readonly class Response
{
    private function __construct(
        private int $statusCode,
        private string $body,
        private ?string $location
    ) {
    }

    public static function html(string $body): self
    {
        return new self(200, $body, null);
    }

    /**
     * @param string $location a fixed path within the application; never a value taken from the request
     */
    public static function redirect(string $location): self
    {
        return new self(302, '', $location);
    }

    /**
     * For a path that has moved for good, so a bookmark is corrected rather
     * than followed again on every visit.
     *
     * @param string $location a fixed path within the application; never a value taken from the request
     */
    public static function movedPermanently(string $location): self
    {
        return new self(301, '', $location);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function location(): ?string
    {
        return $this->location;
    }

    public function send(): void
    {
        if (null !== $this->location) {
            header('Location: ' . $this->location, true, $this->statusCode);

            return;
        }

        http_response_code($this->statusCode);
        echo $this->body;
    }
}
