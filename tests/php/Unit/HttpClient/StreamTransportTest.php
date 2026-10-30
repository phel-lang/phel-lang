<?php

declare(strict_types=1);

namespace PhelTest\Unit\HttpClient;

use InvalidArgumentException;
use Phel\HttpClient\StreamTransport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class StreamTransportTest extends TestCase
{
    public static function invalid_body_limits(): iterable
    {
        yield 'negative' => [-1];
        yield 'float zero' => [0.0];
        yield 'fraction' => [1.5];
        yield 'overflow float' => [(float) PHP_INT_MAX];
        yield 'numeric string' => ['1'];
        yield 'empty string' => [''];
        yield 'true' => [true];
        yield 'false' => [false];
        yield 'array' => [[]];
    }

    #[DataProvider('invalid_body_limits')]
    public function test_invalid_body_limits_are_rejected_before_opening_a_connection(mixed $limit): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('max_body_bytes must be a non-negative integer or null');

        StreamTransport::send('GET', 'http://127.0.0.1:1/', [], null, ['max_body_bytes' => $limit, 'timeout' => 0.1]);
    }

    #[DataProvider('invalidHeaders')]
    public function test_send_rejects_invalid_headers_before_opening_a_connection(string $name, string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid HTTP header');

        StreamTransport::send('GET', 'http://127.0.0.1:1/', [$name => $value], null, ['timeout' => 0.1]);
    }

    public static function invalidHeaders(): iterable
    {
        yield 'CRLF value' => ['x-test', "ok\r\nX-Admin: yes"];
        yield 'CR value' => ['x-test', "ok\rX-Admin: yes"];
        yield 'LF value' => ['x-test', "ok\nX-Admin: yes"];
        yield 'CRLF name' => ["x-test\r\nX-Admin", 'yes'];
        yield 'colon name' => ['x:test', 'yes'];
        yield 'space name' => ['x test', 'yes'];
        yield 'tab name' => ["x\ttest", 'yes'];
        yield 'empty name' => ['', 'yes'];
        yield 'separator name' => ['x(test)', 'yes'];
        yield 'non-ascii name' => ['x-é', 'yes'];
    }

    public function test_send_throws_runtime_exception_on_unreachable_url(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('#^HTTP request to http://127\.0\.0\.1:1/ failed: #');

        // Port 1 is reserved/closed, so the connection is refused immediately.
        StreamTransport::send('GET', 'http://127.0.0.1:1/', [], null, ['timeout' => 0.5]);
    }

    #[DataProvider('nonHttpUrls')]
    public function test_send_refuses_a_url_that_is_not_http(string $url): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only http and https URLs are supported, got: ' . $url);

        StreamTransport::send('GET', $url, [], null, []);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonHttpUrls(): iterable
    {
        yield 'file' => ['file:///etc/hosts'];
        yield 'php filter' => ['php://filter/read=convert.base64-encode/resource=/etc/hosts'];
        yield 'data' => ['data://text/plain,hello'];
        yield 'no scheme' => ['/etc/hosts'];
    }
}
