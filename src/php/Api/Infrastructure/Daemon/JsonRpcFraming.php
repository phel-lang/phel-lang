<?php

declare(strict_types=1);

namespace Phel\Api\Infrastructure\Daemon;

use RuntimeException;

use function is_array;
use function json_decode;
use function json_encode;
use function rtrim;
use function strlen;

/**
 * Newline-delimited JSON framing for the Api daemon.
 *
 * Each request is a single JSON object on a line terminated by \n; responses
 * use the same framing. This is simpler than Content-Length framing and works
 * cleanly over stdio for editors that prefer line-oriented IPC.
 */
final class JsonRpcFraming
{
    /**
     * @param resource $stream
     *
     * @return array<string, mixed>|null
     */
    public function readMessage($stream): ?array
    {
        $line = fgets($stream);
        if ($line === false) {
            return null;
        }

        $trimmed = rtrim($line, "\r\n");
        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid JSON payload received by daemon');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @param resource             $stream
     * @param array<string, mixed> $message
     */
    public function writeMessage($stream, array $message): void
    {
        $json = json_encode($message, JSON_THROW_ON_ERROR);
        fwrite($stream, $json . "\n");
        fflush($stream);
    }

    public function frameLength(string $json): int
    {
        return strlen($json) + 1;
    }
}
