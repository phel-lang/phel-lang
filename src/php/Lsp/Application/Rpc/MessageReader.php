<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Rpc;

use RuntimeException;
use Throwable;

use function in_array;
use function is_array;
use function strlen;

/**
 * @internal
 */
final class MessageReader
{
    private const int MAX_HEADER_LINES = 32;

    private const int READ_TIMEOUT_MICRO = 200_000;

    /** @var resource|null */
    private $stream;

    private string $buffer = '';

    private ?int $contentLength = null;

    private int $headerLines = 0;

    private bool $readingBody = false;

    /**
     * @param resource $stream
     *
     * @return array<string, mixed>|false|null false on an input failure, null at EOF, [] on idle
     */
    public function read($stream): array|false|null
    {
        if ($this->stream !== $stream) {
            $this->stream = $stream;
            $this->buffer = '';
            $this->resetFrame();
        }

        while (true) {
            $message = $this->bufferedMessage();
            if ($message !== null) {
                return $message;
            }

            if (feof($stream)) {
                $this->buffer = '';
                $this->resetFrame();
                return null;
            }

            $metadata = stream_get_meta_data($stream);
            if (!in_array($metadata['stream_type'], ['MEMORY', 'TEMP'], true)) {
                $read = [$stream];
                $write = null;
                $except = null;
                try {
                    $ready = @stream_select($read, $write, $except, 0, self::READ_TIMEOUT_MICRO);
                } catch (Throwable) {
                    return false;
                }

                if ($ready === false) {
                    return false;
                }

                if ($ready === 0) {
                    return [];
                }
            }

            $wasBlocking = $metadata['blocked'];
            if ($wasBlocking) {
                stream_set_blocking($stream, false);
            }

            try {
                $chunk = fread($stream, 8192);
            } finally {
                if ($wasBlocking) {
                    stream_set_blocking($stream, true);
                }
            }

            if ($chunk === false) {
                return false;
            }

            $this->buffer .= $chunk;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function bufferedMessage(): ?array
    {
        while (!$this->readingBody) {
            $newline = strpos($this->buffer, "\n");
            if ($newline === false) {
                return null;
            }

            $line = trim(substr($this->buffer, 0, $newline));
            $this->buffer = substr($this->buffer, $newline + 1);
            if ($line === '') {
                $this->readingBody = true;
                break;
            }

            if (++$this->headerLines >= self::MAX_HEADER_LINES) {
                $this->resetFrame();
                throw new RuntimeException('LSP header block exceeded maximum size.');
            }

            if (preg_match('/^Content-Length:\s*(\d+)\s*$/i', $line, $matches) === 1) {
                $this->contentLength = (int) $matches[1];
            }
        }

        $length = $this->contentLength ?? 0;
        if (strlen($this->buffer) < $length) {
            return null;
        }

        $body = substr($this->buffer, 0, $length);
        $this->buffer = substr($this->buffer, $length);
        $this->resetFrame();
        if ($length === 0) {
            return [];
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid JSON body in LSP message.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private function resetFrame(): void
    {
        $this->contentLength = null;
        $this->headerLines = 0;
        $this->readingBody = false;
    }
}
