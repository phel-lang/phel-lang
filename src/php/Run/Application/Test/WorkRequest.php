<?php

declare(strict_types=1);

namespace Phel\Run\Application\Test;

use Phel\Shared\ScalarCoercion;

use function is_array;
use function is_string;

/**
 * Decoded parent-to-worker frame. Sibling of {@see WorkerResult}: both
 * value objects encapsulate the JSON wire format so the orchestrator
 * and the worker subcommand never reach into raw `array<string, mixed>`
 * payloads directly.
 *
 * @phpstan-import-type LoadEntry from LoadOrderResolver
 *
 * @internal
 */
final readonly class WorkRequest
{
    /**
     * @param list<LoadEntry> $loadOrder files to evaluate before running `$ns`,
     *                                   dependencies first, `$file` last; empty
     *                                   when the parent sent none
     */
    public function __construct(
        public int $index,
        public string $ns,
        public string $file,
        public string $options,
        public array $loadOrder = [],
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
            self::decodeLoadOrder($frame[FrameKey::LOAD_ORDER] ?? null),
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

    /**
     * @return list<LoadEntry>
     */
    private static function decodeLoadOrder(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $entries = [];
        foreach ($raw as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $ns = $entry['ns'] ?? null;
            $file = $entry['file'] ?? null;
            if (!is_string($ns)) {
                continue;
            }

            if (!is_string($file)) {
                continue;
            }

            if ($ns === '') {
                continue;
            }

            if ($file === '') {
                continue;
            }

            $entries[] = ['ns' => $ns, 'file' => $file];
        }

        return $entries;
    }
}
