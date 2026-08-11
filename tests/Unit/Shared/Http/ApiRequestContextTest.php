<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Http;

use MailWatch\Shared\Http\ApiRequestContext;
use PHPUnit\Framework\TestCase;

final class ApiRequestContextTest extends TestCase
{
    public function testItPreservesASafeCallerSuppliedRequestId(): void
    {
        $request = ApiRequestContext::fromServer(['HTTP_X_REQUEST_ID' => 'mailwatch.request-123']);

        self::assertSame('mailwatch.request-123', $request->requestId());
        self::assertSame(['X-Request-ID: mailwatch.request-123'], $request->responseHeaders());
    }

    public function testItReplacesAnUnsafeRequestId(): void
    {
        $request = ApiRequestContext::fromServer(['HTTP_X_REQUEST_ID' => "unsafe\r\nInjected: header"]);

        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $request->requestId());
        self::assertStringNotContainsString('unsafe', $request->requestId());
    }

    public function testItGeneratesARequestIdWhenTheCallerDoesNotSupplyOne(): void
    {
        $first = ApiRequestContext::fromServer([])->requestId();
        $second = ApiRequestContext::fromServer([])->requestId();

        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $first);
        self::assertNotSame($first, $second);
    }
}
