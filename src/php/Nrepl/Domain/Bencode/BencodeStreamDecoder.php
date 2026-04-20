<?php

declare(strict_types=1);

namespace Phel\Nrepl\Domain\Bencode;

use function strlen;
use function substr;

/**
 * Stateful decoder for stream-oriented bencode consumption.
 *
 * Accumulates arbitrary byte chunks and yields complete messages
 * as they become available. Leaves partial suffixes in the buffer
 * until the next chunk arrives.
 */
final class BencodeStreamDecoder
{
    private string $buffer = '';

    private readonly BencodeDecoder $decoder;

    public function __construct(?BencodeDecoder $decoder = null)
    {
        $this->decoder = $decoder ?? new BencodeDecoder();
    }

    public function feed(string $chunk): void
    {
        $this->buffer .= $chunk;
    }

    /**
     * Pop all complete messages currently in the buffer.
     *
     * @return list<mixed>
     */
    public function drain(): array
    {
        $messages = [];

        while ($this->buffer !== '') {
            try {
                [$value, $consumed] = $this->decoder->decodeWithLength($this->buffer);
            } catch (BencodeException) {
                // Partial frame: keep buffered, wait for more bytes.
                break;
            }

            if ($consumed <= 0 || $consumed > strlen($this->buffer)) {
                break;
            }

            $messages[] = $value;
            $this->buffer = substr($this->buffer, $consumed);
        }

        return $messages;
    }

    public function buffer(): string
    {
        return $this->buffer;
    }
}
