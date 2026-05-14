<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

/**
 * Decoded parent-to-worker frame. Plain value object so
 * {@see TestWorkerCommand::handleWork()} can stay focused on dispatch
 * logic instead of frame-shape coercion.
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
            (int) ($frame['index'] ?? -1),
            (string) ($frame['ns'] ?? ''),
            (string) ($frame['file'] ?? ''),
            (string) ($frame['options'] ?? '{}'),
        );
    }

    /**
     * Common shape of every result frame: type/index/ns echoed back so the
     * orchestrator can route the response to the right slot.
     *
     * @return array{type: string, index: int, ns: string}
     */
    public function baseResponse(): array
    {
        return [
            'type' => 'result',
            'index' => $this->index,
            'ns' => $this->ns,
        ];
    }
}
