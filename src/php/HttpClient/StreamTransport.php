<?php

declare(strict_types=1);

namespace Phel\HttpClient;

use RuntimeException;

use function sprintf;

/**
 * HTTP transport using PHP's built-in stream context.
 * No external dependencies required (no cURL, no Guzzle).
 */
final class StreamTransport
{
    /**
     * @param array<string, string> $headers Header name => value pairs
     * @param array<string, mixed>  $options Transport options (timeout, follow_redirects, verify_ssl)
     *
     * @return array{status: int, headers: array<string, string>, body: string, version: string, reason: string}
     */
    public static function send(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        array $options,
    ): array {
        $context = self::buildContext($method, $headers, $body, $options);

        $http_response_header = [];
        $responseBody = @file_get_contents($url, false, $context);

        if ($responseBody === false) {
            $error = error_get_last();
            throw new RuntimeException(
                sprintf('HTTP request to %s failed: %s', $url, $error['message'] ?? 'Unknown error'),
            );
        }

        $parsed = ResponseParser::parse($http_response_header);

        return [
            'status' => $parsed['status'],
            'headers' => $parsed['headers'],
            'body' => $responseBody,
            'version' => $parsed['version'],
            'reason' => $parsed['reason'],
        ];
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>  $options
     *
     * @return resource
     */
    private static function buildContext(
        string $method,
        array $headers,
        ?string $body,
        array $options,
    ) {
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = sprintf('%s: %s', $name, $value);
        }

        $timeout = (float) ($options['timeout'] ?? 30.0);
        $followRedirects = (bool) ($options['follow_redirects'] ?? true);
        $verifySsl = (bool) ($options['verify_ssl'] ?? true);

        return stream_context_create([
            'http' => [
                'method' => strtoupper($method),
                'header' => implode("\r\n", $headerLines),
                'content' => $body ?? '',
                'timeout' => $timeout,
                'follow_location' => $followRedirects ? 1 : 0,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => $verifySsl,
                'verify_peer_name' => $verifySsl,
            ],
        ]);
    }
}
