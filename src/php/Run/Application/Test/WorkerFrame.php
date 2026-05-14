<?php

declare(strict_types=1);

namespace Phel\Run\Application\Test;

use JsonException;
use RuntimeException;

use function dechex;
use function fread;
use function is_array;
use function json_decode;
use function json_encode;
use function str_pad;
use function stream_set_blocking;
use function strlen;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const STR_PAD_LEFT;

/**
 * Length-prefixed JSON framing used between TestCommand (parent) and
 * the long-lived `_test-worker` subprocesses.
 *
 * Wire format per frame:
 *
 *     <8 ASCII hex digits>\n<payload bytes>
 *
 * where the hex digits encode the byte length of the JSON payload. The
 * trailing newline after the header keeps frames human-readable when
 * pipes are tee'd to a log.
 */
final class WorkerFrame
{
    private const int HEADER_HEX_DIGITS = 8;

    /**
     * @param array<string, mixed> $payload
     */
    public static function encode(array $payload): string
    {
        try {
            $body = json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException $jsonException) {
            throw new RuntimeException('Failed to encode worker frame: ' . $jsonException->getMessage(), 0, $jsonException);
        }

        $hex = str_pad(dechex(strlen($body)), self::HEADER_HEX_DIGITS, '0', STR_PAD_LEFT);

        return $hex . "\n" . $body;
    }

    /**
     * Read one full frame from a blocking stream. Returns null at EOF.
     *
     * @param resource $stream
     *
     * @return array<string, mixed>|null
     */
    public static function readBlocking($stream): ?array
    {
        @stream_set_blocking($stream, true);

        $header = '';
        while (strlen($header) < self::HEADER_HEX_DIGITS + 1) {
            $chunk = fread($stream, self::HEADER_HEX_DIGITS + 1 - strlen($header));
            if ($chunk === false || $chunk === '') {
                return null;
            }

            $header .= $chunk;
        }

        $length = (int) hexdec(substr($header, 0, self::HEADER_HEX_DIGITS));
        $body = '';
        while (strlen($body) < $length) {
            $chunk = fread($stream, $length - strlen($body));
            if ($chunk === false || $chunk === '') {
                return null;
            }

            $body .= $chunk;
        }

        return self::decodeBody($body);
    }

    /**
     * @return array<string, mixed>
     */
    public static function decodeBody(string $body): array
    {
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new RuntimeException('Failed to decode worker frame: ' . $jsonException->getMessage(), 0, $jsonException);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('Worker frame payload must decode to an object.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    public static function headerSize(): int
    {
        return self::HEADER_HEX_DIGITS + 1;
    }
}
