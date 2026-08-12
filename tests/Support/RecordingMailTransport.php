<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

final class RecordingMailTransport implements TransportInterface
{
    /** @var list<RawMessage> */
    public array $sent = [];

    public function __construct(private readonly ?\Throwable $failure = null)
    {
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        if (null !== $this->failure) {
            throw $this->failure;
        }

        $this->sent[] = $message;

        return null;
    }

    public function __toString(): string
    {
        return 'recording://mailwatch';
    }
}
