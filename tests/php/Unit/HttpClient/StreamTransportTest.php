<?php

declare(strict_types=1);

namespace PhelTest\Unit\HttpClient;

use Phel\HttpClient\StreamTransport;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class StreamTransportTest extends TestCase
{
    public function test_send_throws_runtime_exception_on_unreachable_url(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('#^HTTP request to http://127\.0\.0\.1:1/ failed: #');

        // Port 1 is reserved/closed, so the connection is refused immediately.
        StreamTransport::send('GET', 'http://127.0.0.1:1/', [], null, ['timeout' => 0.5]);
    }

    public function test_send_returns_body_for_readable_stream(): void
    {
        // The data:// wrapper is honoured by file_get_contents() and lets us
        // exercise the non-failure branch of send() fully offline. It does not
        // populate $http_response_header, so ResponseParser falls back to its
        // defaults — which is the documented behaviour for missing status lines.
        $result = StreamTransport::send('GET', 'data://text/plain,hello-world', [], null, []);

        self::assertSame('hello-world', $result['body']);
    }

    public function test_send_uses_response_parser_defaults_when_no_status_line(): void
    {
        $result = StreamTransport::send('GET', 'data://text/plain,payload', [], null, []);

        self::assertSame(200, $result['status']);
        self::assertSame('1.1', $result['version']);
        self::assertSame('OK', $result['reason']);
        self::assertSame([], $result['headers']);
    }

    public function test_send_returns_expected_shape(): void
    {
        $result = StreamTransport::send('GET', 'data://text/plain,x', [], null, []);

        self::assertArrayHasKey('status', $result);
        self::assertArrayHasKey('headers', $result);
        self::assertArrayHasKey('body', $result);
        self::assertArrayHasKey('version', $result);
        self::assertArrayHasKey('reason', $result);
    }
}
