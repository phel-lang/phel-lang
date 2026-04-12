<?php

declare(strict_types=1);

namespace PhelTest\Unit\HttpClient;

use Phel\HttpClient\ResponseParser;
use PHPUnit\Framework\TestCase;

final class ResponseParserTest extends TestCase
{
    public function test_parse_status_line(): void
    {
        $result = ResponseParser::parse([
            'HTTP/1.1 200 OK',
        ]);

        self::assertSame(200, $result['status']);
        self::assertSame('1.1', $result['version']);
        self::assertSame('OK', $result['reason']);
    }

    public function test_parse_404_status(): void
    {
        $result = ResponseParser::parse([
            'HTTP/1.1 404 Not Found',
        ]);

        self::assertSame(404, $result['status']);
        self::assertSame('Not Found', $result['reason']);
    }

    public function test_parse_headers(): void
    {
        $result = ResponseParser::parse([
            'HTTP/1.1 200 OK',
            'Content-Type: application/json',
            'X-Request-Id: abc-123',
        ]);

        self::assertSame('application/json', $result['headers']['content-type']);
        self::assertSame('abc-123', $result['headers']['x-request-id']);
    }

    public function test_parse_header_with_colon_in_value(): void
    {
        $result = ResponseParser::parse([
            'HTTP/1.1 200 OK',
            'Location: https://example.com:8080/path',
        ]);

        self::assertSame('https://example.com:8080/path', $result['headers']['location']);
    }

    public function test_parse_empty_headers_returns_defaults(): void
    {
        $result = ResponseParser::parse([]);

        self::assertSame(200, $result['status']);
        self::assertSame('1.1', $result['version']);
        self::assertSame('OK', $result['reason']);
        self::assertSame([], $result['headers']);
    }

    public function test_parse_redirect_chain_uses_last_status(): void
    {
        $result = ResponseParser::parse([
            'HTTP/1.1 301 Moved Permanently',
            'Location: https://example.com/new',
            'HTTP/1.1 200 OK',
            'Content-Type: text/html',
        ]);

        self::assertSame(200, $result['status']);
        self::assertSame('text/html', $result['headers']['content-type']);
    }

    public function test_parse_http2_version(): void
    {
        $result = ResponseParser::parse([
            'HTTP/2.0 204 No Content',
        ]);

        self::assertSame(204, $result['status']);
        self::assertSame('2.0', $result['version']);
        self::assertSame('No Content', $result['reason']);
    }

    public function test_parse_status_with_no_reason(): void
    {
        $result = ResponseParser::parse([
            'HTTP/1.1 204',
        ]);

        self::assertSame(204, $result['status']);
        self::assertSame('', $result['reason']);
    }

    public function test_parse_header_names_are_lowercased(): void
    {
        $result = ResponseParser::parse([
            'HTTP/1.1 200 OK',
            'Content-Type: text/html',
            'CACHE-CONTROL: no-cache',
        ]);

        self::assertArrayHasKey('content-type', $result['headers']);
        self::assertArrayHasKey('cache-control', $result['headers']);
    }

    public function test_parse_last_header_wins_for_duplicate_names(): void
    {
        $result = ResponseParser::parse([
            'HTTP/1.1 200 OK',
            'X-Custom: first',
            'X-Custom: second',
        ]);

        self::assertSame('second', $result['headers']['x-custom']);
    }

    public function test_parse_malformed_status_line_is_skipped(): void
    {
        $result = ResponseParser::parse([
            '200 OK',
            'Content-Type: text/plain',
        ]);

        self::assertSame(200, $result['status']);
        self::assertSame('text/plain', $result['headers']['content-type']);
    }

    public function test_parse_line_without_colon_is_ignored(): void
    {
        $result = ResponseParser::parse([
            'HTTP/1.1 200 OK',
            'not-a-header-line',
        ]);

        self::assertSame([], $result['headers']);
    }

    public function test_parse_header_with_empty_value(): void
    {
        $result = ResponseParser::parse([
            'HTTP/1.1 200 OK',
            'X-Empty:   ',
        ]);

        self::assertSame('', $result['headers']['x-empty']);
    }

    public function test_parse_500_server_error(): void
    {
        $result = ResponseParser::parse([
            'HTTP/1.1 500 Internal Server Error',
        ]);

        self::assertSame(500, $result['status']);
        self::assertSame('Internal Server Error', $result['reason']);
    }
}
