<?php

declare(strict_types=1);

namespace Phel\Run\Application\Test;

use function is_array;
use function is_string;
use function sprintf;

/**
 * Decoded worker-to-parent result frame. Plain value object so the
 * orchestrator's dispatch loop can hand around a typed record instead
 * of raw `array<string, mixed>` dictionaries.
 */
final readonly class WorkerResult
{
    /**
     * @param list<string> $failedTests
     */
    public function __construct(
        public int $index,
        public string $ns,
        public bool $ok,
        public string $output,
        public array $failedTests,
    ) {}

    /**
     * @param array<string, mixed> $frame
     */
    public static function fromFrame(array $frame): self
    {
        return new self(
            (int) ($frame['index'] ?? -1),
            (string) ($frame['ns'] ?? ''),
            (bool) ($frame['ok'] ?? false),
            (string) ($frame['output'] ?? ''),
            self::extractStringList($frame['failed-tests'] ?? null),
        );
    }

    /**
     * Synthetic result for a worker that died before responding. Output
     * carries any captured stderr so the user can see why.
     */
    public static function fromCrash(int $index, string $ns, string $stderr): self
    {
        return new self(
            $index,
            $ns,
            false,
            sprintf("Worker died while running %s.\n%s", $ns, $stderr),
            [],
        );
    }

    /**
     * @return list<string>
     */
    private static function extractStringList(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $entry) {
            if (is_string($entry) && $entry !== '') {
                $out[] = $entry;
            }
        }

        return $out;
    }
}
