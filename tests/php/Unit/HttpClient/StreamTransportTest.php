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
