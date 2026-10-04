<?php

declare(strict_types=1);

namespace PhelTest\Integration\HttpClient;

use InvalidArgumentException;
use Phel\HttpClient\StreamTransport;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function json_decode;
use function rawurlencode;

final class StreamTransportRedirectTest extends TestCase
{
    private const array SECRET_HEADERS = [
        'x-api-key' => 'secret-key',
        'authorization' => 'Bearer secret-token',
        'accept' => 'application/json',
    ];

    private static LocalHttpServer $origin;

    private static LocalHttpServer $other;

    public static function setUpBeforeClass(): void
    {
        self::$origin = LocalHttpServer::start();
        self::$other = LocalHttpServer::start();
    }

    public static function tearDownAfterClass(): void
    {
        self::$origin->stop();
        self::$other->stop();
    }

    public function test_a_redirect_to_another_origin_drops_custom_and_credential_headers(): void
    {
        $echo = $this->send('GET', $this->redirectTo(self::$other->url('/echo')));

        self::assertSame('/echo', $echo['path']);
        self::assertArrayNotHasKey('x-api-key', $echo['headers']);
        self::assertArrayNotHasKey('authorization', $echo['headers']);
        self::assertSame('application/json', $echo['headers']['accept']);
    }

    public function test_a_plain_request_returns_status_headers_and_body(): void
    {
        $result = StreamTransport::send('GET', self::$origin->url('/echo'), [], null, []);

        self::assertSame(200, $result['status']);
        self::assertSame('application/json', $result['headers']['content-type']);
        self::assertSame('/echo', json_decode($result['body'], true)['path']);
    }

    public function test_a_redirect_within_the_origin_keeps_every_header(): void
    {
        $echo = $this->send('GET', $this->redirectTo('/echo'));

        self::assertSame('/echo', $echo['path']);
        self::assertSame('secret-key', $echo['headers']['x-api-key']);
        self::assertSame('Bearer secret-token', $echo['headers']['authorization']);
    }

    public function test_a_query_only_location_keeps_the_current_path(): void
    {
        $echo = $this->send('GET', self::$origin->url('/items/redirect?to=' . rawurlencode('?page=2')));

        self::assertSame('/items/redirect', $echo['path']);
        self::assertSame('page=2', $echo['query']);
    }

    public function test_a_relative_location_resolves_against_the_current_directory(): void
    {
        $echo = $this->send('GET', self::$origin->url('/a/b/redirect?to=' . rawurlencode('../echo?x=1')));

        self::assertSame('/a/echo', $echo['path']);
        self::assertSame('x=1', $echo['query']);
    }

    public function test_a_303_turns_a_post_into_a_get_without_its_body(): void
    {
        $echo = $this->send('POST', $this->redirectTo('/echo', 303), '{"a":1}');

        self::assertSame('GET', $echo['method']);
        self::assertSame('', $echo['body']);
    }

    public function test_a_redirect_that_drops_the_body_drops_its_headers(): void
    {
        $result = StreamTransport::send(
            'POST',
            $this->redirectTo('/echo', 303),
            ['content-type' => 'application/json', 'content-length' => '7'],
            '{"a":1}',
            ['timeout' => 5],
        );

        /** @var array{headers: array<string, string>} $echo */
        $echo = json_decode($result['body'], true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('content-length', $echo['headers']);
        self::assertArrayNotHasKey('content-type', $echo['headers']);
    }

    public function test_a_303_keeps_a_head_request_a_head(): void
    {
        $result = StreamTransport::send('HEAD', $this->redirectTo('/echo', 303), [], null, ['timeout' => 5]);

        self::assertSame('HEAD', $result['headers']['x-method']);
    }

    public function test_a_307_keeps_the_method_and_the_body(): void
    {
        $echo = $this->send('POST', $this->redirectTo('/echo', 307), '{"a":1}');

        self::assertSame('POST', $echo['method']);
        self::assertSame('{"a":1}', $echo['body']);
    }

    public function test_a_redirect_is_returned_as_is_when_redirects_are_off(): void
    {
        $result = StreamTransport::send('GET', $this->redirectTo('/echo'), [], null, ['follow_redirects' => false]);

        self::assertSame(302, $result['status']);
        self::assertSame('/echo', $result['headers']['location']);
    }

    public function test_a_redirect_loop_stops(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Too many redirects');

        StreamTransport::send('GET', self::$origin->url('/loop'), [], null, []);
    }

    public function test_a_redirect_to_a_non_http_scheme_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only http and https URLs');

        StreamTransport::send('GET', $this->redirectTo('file:///etc/hosts'), [], null, []);
    }

    /**
     * @return array{method: string, path: string, query: string, headers: array<string, string>, body: string}
     */
    private function send(string $method, string $url, ?string $body = null): array
    {
        $result = StreamTransport::send($method, $url, self::SECRET_HEADERS, $body, []);

        /** @var array{method: string, path: string, query: string, headers: array<string, string>, body: string} */
        return json_decode($result['body'], true, flags: JSON_THROW_ON_ERROR);
    }

    private function redirectTo(string $target, int $code = 302): string
    {
        return self::$origin->url('/redirect?code=' . $code . '&to=' . rawurlencode($target));
    }
}
