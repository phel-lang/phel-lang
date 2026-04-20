<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Rpc;

use RuntimeException;

use function feof;
use function fgets;
use function fread;
use function is_array;
use function json_decode;
use function preg_match;
use function stream_set_timeout;
use function strlen;
use function trim;

/**
 * LSP uses JSON-RPC 2.0 framed with HTTP-like headers:
 *
 *   Content-Length: <n>\r\n
 *   [Content-Type: application/vscode-jsonrpc; charset=utf-8\r\n]
 *   \r\n
 *   <body of exactly n bytes>
 *
 * This reader parses a single message at a time from a PHP stream
 * and returns the decoded JSON body as an associative array.
 */
final class MessageReader
{
    private const int MAX_HEADER_LINES = 32;

    private const int READ_TIMEOUT_SECONDS = 0;

    private const int READ_TIMEOUT_MICRO = 200_000;

    /**
     * @param resource $stream
     *
     * @return array<string, mixed>|null null when the stream is closed, [] on a
     *                                   heartbeat/empty read
     */
    public function read($stream): ?array
    {
        if (feof($stream)) {
            return null;
        }

        @stream_set_timeout($stream, self::READ_TIMEOUT_SECONDS, self::READ_TIMEOUT_MICRO);

        $contentLength = $this->readHeaders($stream);
        if ($contentLength === null) {
            return null;
        }

        if ($contentLength === 0) {
            return [];
        }

        $body = $this->readBody($stream, $contentLength);
        if ($body === null) {
            return null;
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid JSON body in LSP message.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @param resource $stream
     */
    private function readHeaders($stream): ?int
    {
        $contentLength = null;
        $lines = 0;

        while ($lines < self::MAX_HEADER_LINES) {
            $line = fgets($stream);
            if ($line === false) {
                return null;
            }

            $trimmed = trim($line);
            if ($trimmed === '') {
                return $contentLength ?? 0;
            }

            if (preg_match('/^Content-Length:\s*(\d+)\s*$/i', $trimmed, $m)) {
                $contentLength = (int) $m[1];
            }

            // Other headers (e.g. Content-Type) are accepted and ignored.
            ++$lines;
        }

        throw new RuntimeException('LSP header block exceeded maximum size.');
    }

    /**
     * @param resource $stream
     */
    private function readBody($stream, int $length): ?string
    {
        $body = '';
        $remaining = $length;

        while ($remaining > 0) {
            $chunk = fread($stream, $remaining);
            if ($chunk === false || $chunk === '') {
                if (feof($stream)) {
                    return null;
                }

                continue;
            }

            $body .= $chunk;
            $remaining -= strlen($chunk);
        }

        return $body;
    }
}
