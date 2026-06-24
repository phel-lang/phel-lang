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

    /** Sentinel: the read window elapsed before a header block arrived. */
    private const int TIMED_OUT = -1;

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

        // No complete header block arrived within the read window. The client
        // is simply idle (editors stay connected but quiet between requests),
        // not gone, so return a heartbeat and let the caller poll again rather
        // than mistaking the timeout for end-of-stream.
        if ($contentLength === self::TIMED_OUT) {
            return [];
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
     *
     * @return int|null content length (>=0), {@see self::TIMED_OUT} when the
     *                  read window elapsed before any data arrived, or null at
     *                  end-of-stream
     */
    private function readHeaders($stream): ?int
    {
        $contentLength = null;
        $lines = 0;
        $started = false;

        while ($lines < self::MAX_HEADER_LINES) {
            $line = fgets($stream);
            if ($line === false) {
                // Distinguish a real EOF (client gone) from a read timeout
                // (idle but still connected). A timeout before any byte of the
                // block is a heartbeat; once a block has started we keep waiting
                // for the rest so we never drop a partially read header.
                if (feof($stream)) {
                    return null;
                }

                if (!$started) {
                    return self::TIMED_OUT;
                }

                continue;
            }

            $started = true;

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
