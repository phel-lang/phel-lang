<?php

declare(strict_types=1);

namespace Phel\HttpClient;

/**
 * Parses raw HTTP response headers from PHP's $http_response_header
 * into a structured associative array.
 *
 * Header names are lowercased. For duplicate header names (e.g. Set-Cookie),
 * only the last value is kept. Redirect chains are handled by resetting
 * status/version/reason on each new HTTP status line encountered.
 */
final class ResponseParser
{
    /**
     * @param list<string> $rawHeaders Raw header lines from $http_response_header
     *
     * @return array{status: int, version: string, reason: string, headers: array<string, string>}
     */
    public static function parse(array $rawHeaders): array
    {
        $status = 200;
        $version = '1.1';
        $reason = 'OK';
        $headers = [];

        foreach ($rawHeaders as $line) {
            if (preg_match('#^HTTP/(\d+\.\d+)\s+(\d+)\s*(.*)$#', $line, $matches) === 1) {
                $version = $matches[1];
                $status = (int) $matches[2];
                $reason = trim($matches[3]);
                continue;
            }

            $colonPos = strpos($line, ':');
            if ($colonPos !== false) {
                $name = strtolower(trim(substr($line, 0, $colonPos)));
                $value = trim(substr($line, $colonPos + 1));
                $headers[$name] = $value;
            }
        }

        return [
            'status' => $status,
            'version' => $version,
            'reason' => $reason,
            'headers' => $headers,
        ];
    }
}
