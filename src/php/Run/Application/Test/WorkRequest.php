<?php

declare(strict_types=1);

namespace Phel\Run\Application\Test;

use Phel\Shared\ScalarCoercion;

/**
 * Decoded parent-to-worker frame. Sibling of {@see WorkerResult}: both
 * value objects encapsulate the JSON wire format so the orchestrator
 * and the worker subcommand never reach into raw `array<string, mixed>`
 * payloads directly.
 */
final readonly class WorkRequest
{
    public function __construct(
        public int $index,
        public string $ns,
        public string $file,
        public string $options,
    ) {}

    /**
     * @param array<string, mixed> $frame
     */
    public static function fromFrame(array $frame): self
    {
        return new self(
            ScalarCoercion::toInt($frame[FrameKey::INDEX] ?? null, -1),
            ScalarCoercion::toString($frame[FrameKey::NS] ?? null),
            ScalarCoercion::toString($frame[FrameKey::FILE] ?? null),
            ScalarCoercion::toString($frame[FrameKey::OPTIONS] ?? null, '{}'),
        );
    }

    /**
     * Common shape of every result frame: type/index/ns echoed back so
     * the orchestrator can route the response to the right slot.
     *
     * @return array{type: string, index: int, ns: string}
     */
    public function baseResponse(): array
    {
        return [
            FrameKey::TYPE => FrameKey::TYPE_RESULT,
            FrameKey::INDEX => $this->index,
            FrameKey::NS => $this->ns,
        ];
    }
}
