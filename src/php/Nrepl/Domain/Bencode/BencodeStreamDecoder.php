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
 *
 * @internal
 */
final class BencodeStreamDecoder
{
    private string $buffer = '';

    private ?BencodeException $pendingError = null;

    private bool $hasNewInput = false;

    private int $bytesSinceMessage = 0;

    private readonly BencodeDecoder $decoder;

    public function __construct(?BencodeDecoder $decoder = null)
    {
        $this->decoder = $decoder ?? new BencodeDecoder();
    }

    public function feed(string $chunk): void
    {
        if ($chunk === '') {
            return;
        }

        if ($this->bytesSinceMessage + strlen($chunk) > 16 * 1024 * 1024) {
            $this->buffer = '';
            $this->bytesSinceMessage = 0;
            throw BencodeException::bufferLimitExceeded();
        }

        $this->buffer .= $chunk;
        $this->bytesSinceMessage += strlen($chunk);
        $this->hasNewInput = true;
    }

    /**
     * Pop all complete messages currently in the buffer.
     *
     * @return list<array<string, mixed>|int|list<mixed>|string>
     */
    public function drain(): array
    {
        if ($this->pendingError instanceof BencodeException) {
            $exception = $this->pendingError;
            $this->pendingError = null;
            throw $exception;
        }

        if (!$this->hasNewInput) {
            return [];
        }

        $this->hasNewInput = false;
        $messages = [];

        while ($this->buffer !== '') {
            try {
                [$value, $consumed] = $this->decoder->decodeWithLength($this->buffer);

                if ($consumed <= 0 || $consumed > strlen($this->buffer)) {
                    break;
                }

                $messages[] = $value;
                $this->buffer = substr($this->buffer, $consumed);
                $this->bytesSinceMessage = strlen($this->buffer);
            } catch (BencodeException $exception) {
                if ($exception->isIncomplete()) {
                    break;
                }

                $this->buffer = '';
                if ($messages !== []) {
                    $this->pendingError = $exception;
                    break;
                }

                throw $exception;
            }
        }

        return $messages;
    }

    public function buffer(): string
    {
        return $this->buffer;
    }
}
