<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lists\Application;

use MailWatch\Lists\Application\AllowBlockListGateway;
use MailWatch\Lists\Application\GetAllowBlockListSnapshot;
use PHPUnit\Framework\TestCase;

final class GetAllowBlockListSnapshotTest extends TestCase
{
    public function testItNormalizesAndSortsBothLists(): void
    {
        $gateway = new class implements AllowBlockListGateway {
            public function entries(): array
            {
                return [
                    'allowlist' => [
                        ['to_address' => 'Zulu.EXAMPLE', 'from_address' => 'Sender@EXAMPLE.COM'],
                        ['to_address' => 'alpha.example', 'from_address' => 'OTHER@example.com'],
                    ],
                    'blocklist' => [
                        ['to_address' => 'DEFAULT', 'from_address' => 'Blocked@EXAMPLE.COM'],
                    ],
                ];
            }
        };

        $snapshot = (new GetAllowBlockListSnapshot($gateway))->get()->toArray();

        self::assertSame([
            ['to_address' => 'alpha.example', 'from_address' => 'other@example.com'],
            ['to_address' => 'zulu.example', 'from_address' => 'sender@example.com'],
        ], $snapshot['allowlist']);
        self::assertSame([
            ['to_address' => 'default', 'from_address' => 'blocked@example.com'],
        ], $snapshot['blocklist']);
    }
}
