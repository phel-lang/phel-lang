<?php

declare(strict_types=1);

namespace Phel\Nrepl\Domain\Transport;

use Phel\Nrepl\Domain\Bencode\BencodeEncoder;
use Phel\Nrepl\Domain\Bencode\BencodeStreamDecoder;

use function fclose;
use function feof;
use function fread;
use function fwrite;
use function is_resource;
use function stream_set_blocking;
use function strlen;
use function substr;

/**
 * Wraps a live socket resource with bencode framing.
 */
final class ClientConnection
{
    private readonly BencodeStreamDecoder $decoder;

    private readonly BencodeEncoder $encoder;

    /** @var resource|null */
    private mixed $stream;

    /**
     * @param resource $stream
     */
    public function __construct(
        mixed $stream,
        ?BencodeStreamDecoder $decoder = null,
        ?BencodeEncoder $encoder = null,
    ) {
        /** @psalm-suppress RedundantConditionGivenDocblockType */
        if (is_resource($stream)) {
            stream_set_blocking($stream, false);
        }

        $this->stream = $stream;
        $this->decoder = $decoder ?? new BencodeStreamDecoder();
        $this->encoder = $encoder ?? new BencodeEncoder();
    }

    /**
     * Read whatever is available and return any completed bencode messages.
     *
     * @return list<mixed>
     */
    public function readMessages(): array
    {
        if (!is_resource($this->stream)) {
            return [];
        }

        $chunk = @fread($this->stream, 8192);
        if ($chunk === false || $chunk === '') {
            return [];
        }

        $this->decoder->feed($chunk);

        return $this->decoder->drain();
    }

    public function isClosed(): bool
    {
        if (!is_resource($this->stream)) {
            return true;
        }

        return feof($this->stream);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function send(array $payload): void
    {
        if (!is_resource($this->stream)) {
            return;
        }

        $encoded = $this->encoder->encode($payload);
        $total = strlen($encoded);
        $written = 0;
        while ($written < $total) {
            $bytes = @fwrite($this->stream, substr($encoded, $written));
            if ($bytes === false || $bytes === 0) {
                return;
            }

            $written += $bytes;
        }
    }

    public function close(): void
    {
        /** @psalm-suppress InvalidPropertyAssignmentValue */
        if (is_resource($this->stream)) {
            @fclose($this->stream);
        }

        $this->stream = null;
    }
}
