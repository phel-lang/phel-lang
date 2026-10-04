<?php

declare(strict_types=1);

namespace Phel\HttpClient;

use InvalidArgumentException;
use Phel\Shared\ScalarCoercion;
use RuntimeException;

use function array_filter;
use function array_pop;
use function count;
use function end;
use function explode;
use function implode;
use function in_array;
use function parse_url;
use function sprintf;
use function str_starts_with;
use function strcspn;
use function strrpos;
use function strtolower;
use function strtoupper;
use function substr;

use const ARRAY_FILTER_USE_KEY;

/**
 * HTTP transport using PHP's built-in stream context.
 * No external dependencies required (no cURL, no Guzzle).
 *
 * Redirects are followed here rather than by the stream wrapper, which keeps
 * every custom header (`x-api-key` included) when a redirect leaves the origin.
 *
 * @internal
 */
final class StreamTransport
{
    private const int MAX_REDIRECTS = 20;

    private const array REDIRECT_STATUSES = [301, 302, 303, 307, 308];

    /** Headers that describe a body; a redirect that drops the body drops them too. */
    private const array BODY_HEADERS = ['content-length', 'content-type', 'content-encoding'];

    /** Headers a redirect to another origin keeps; it drops the rest, credentials included. */
    private const array CROSS_ORIGIN_HEADERS = ['accept', 'accept-encoding', 'accept-language', 'user-agent', 'content-type'];

    /**
     * @param array<string, string> $headers Header name => value pairs
     * @param array<string, mixed>  $options Transport options (timeout, follow_redirects, verify_ssl)
     *
     * @throws InvalidArgumentException when the URL, or a redirect target, is not http or https
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
        self::assertHttpUrl($url);
        $followRedirects = (bool) ($options['follow_redirects'] ?? true);

        for ($redirects = 0; ; ++$redirects) {
            $response = self::fetch($method, $url, $headers, $body, $options);
            $location = $response['headers']['location'] ?? null;
            if (!$followRedirects || $location === null || !in_array($response['status'], self::REDIRECT_STATUSES, true)) {
                return $response;
            }

            if ($redirects === self::MAX_REDIRECTS) {
                throw new RuntimeException(sprintf('Too many redirects for %s (more than %d)', $url, self::MAX_REDIRECTS));
            }

            $next = self::resolve($url, $location);
            self::assertHttpUrl($next);

            if ($response['status'] === 303 || ($response['status'] <= 302 && strtoupper($method) === 'POST')) {
                $method = 'GET';
                $body = null;
                $headers = array_filter(
                    $headers,
                    static fn(string $name): bool => !in_array(strtolower($name), self::BODY_HEADERS, true),
                    ARRAY_FILTER_USE_KEY,
                );
            }

            if (self::origin($next) !== self::origin($url)) {
                $headers = array_filter(
                    $headers,
                    static fn(string $name): bool => in_array(strtolower($name), self::CROSS_ORIGIN_HEADERS, true),
                    ARRAY_FILTER_USE_KEY,
                );
            }

            $url = $next;
        }
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>  $options
     *
     * @return array{status: int, headers: array<string, string>, body: string, version: string, reason: string}
     */
    private static function fetch(string $method, string $url, array $headers, ?string $body, array $options): array
    {
        $context = self::buildContext($method, $headers, $body, $options);

        $http_response_header = [];
        // Suppress the warning fopen/file_get_contents emits on failure; the
        // precise reason is recovered via error_get_last() so the exception
        // carries an accurate message instead of a generic "request failed".
        $responseBody = @file_get_contents($url, false, $context);

        if ($responseBody === false) {
            // error_get_last() may return null (or an array without a 'message'
            // key) in edge cases, so fall back to a safe default.
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

        // Coerce option values defensively: a numeric-string timeout or a
        // truthy/falsy ssl flag is accepted and normalised here.
        $timeout = ScalarCoercion::toFloat($options['timeout'] ?? null, 30.0);
        $verifySsl = (bool) ($options['verify_ssl'] ?? true);

        // Stream context describes the request: HTTP method, headers, body and
        // timeout under 'http', SSL peer verification (hostname + certificate)
        // under 'ssl'. ignore_errors => true makes file_get_contents() return
        // the body for non-2xx responses instead of false, so error statuses
        // can still be parsed.
        return stream_context_create([
            'http' => [
                'method' => strtoupper($method),
                'header' => implode("\r\n", $headerLines),
                'content' => $body ?? '',
                'timeout' => $timeout,
                'follow_location' => 0,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => $verifySsl,
                'verify_peer_name' => $verifySsl,
            ],
        ]);
    }

    /**
     * `file_get_contents()` also opens `file://`, `php://` and `data://`, so a
     * URL taken from user input would read local files.
     */
    private static function assertHttpUrl(string $url): void
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if ($scheme !== 'http' && $scheme !== 'https') {
            throw new InvalidArgumentException(sprintf('Only http and https URLs are supported, got: %s', $url));
        }
    }

    private static function origin(string $url): string
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        return sprintf('%s://%s:%d', $scheme, strtolower($parts['host'] ?? ''), $port);
    }

    /**
     * Resolves a `Location` against the request URL (RFC 3986, section 5.2).
     */
    private static function resolve(string $base, string $location): string
    {
        if (parse_url($location, PHP_URL_SCHEME) !== null) {
            return $location;
        }

        $parts = parse_url($base);
        $scheme = $parts['scheme'] ?? 'http';
        if (str_starts_with($location, '//')) {
            return $scheme . ':' . $location;
        }

        $authority = $scheme . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $basePath = $parts['path'] ?? '/';
        if ($location === '' || str_starts_with($location, '#')) {
            return $authority . $basePath . (isset($parts['query']) ? '?' . $parts['query'] : '');
        }

        if (str_starts_with($location, '?')) {
            return $authority . $basePath . $location;
        }

        $queryAt = strcspn($location, '?#');
        $path = substr($location, 0, $queryAt);
        $rest = substr($location, $queryAt);
        if (!str_starts_with($path, '/')) {
            $path = substr($basePath, 0, (int) strrpos($basePath, '/') + 1) . $path;
        }

        return $authority . self::removeDotSegments($path) . $rest;
    }

    private static function removeDotSegments(string $path): string
    {
        $segments = explode('/', $path);
        $output = [];
        foreach ($segments as $segment) {
            if ($segment === '.') {
                continue;
            }

            if ($segment === '..') {
                if (count($output) > 1) {
                    array_pop($output);
                }

                continue;
            }

            $output[] = $segment;
        }

        $last = end($segments);
        if ($last === '.' || $last === '..') {
            $output[] = '';
        }

        return implode('/', $output);
    }
}
