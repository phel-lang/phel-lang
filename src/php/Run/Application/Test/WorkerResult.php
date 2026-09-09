<?php

declare(strict_types=1);

namespace Phel\Run\Application\Test;

use Phel\Shared\ScalarCoercion;

use function is_array;
use function is_string;
use function sprintf;

/**
 * Decoded worker-to-parent result frame. Plain value object so the
 * orchestrator's dispatch loop can hand around a typed record instead
 * of raw `array<string, mixed>` dictionaries.
 *
 * @internal
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
        public Counts $counts,
        public WorkerOutcome $outcome = WorkerOutcome::Verdict,
        public bool $focused = false,
    ) {}

    /**
     * Whether re-running this namespace on a fresh worker can change the
     * answer. Only a worker-level fault qualifies; a compile error and a
     * failing test are verdicts about the source.
     */
    public function isRetryable(): bool
    {
        return $this->outcome->isRetryable();
    }

    /**
     * @param array<string, mixed> $frame
     */
    public static function fromFrame(array $frame): self
    {
        $rawCounts = $frame[FrameKey::COUNTS] ?? [];
        if (!is_array($rawCounts)) {
            $rawCounts = [];
        }

        /** @var array<string, mixed> $rawCounts */
        return new self(
            ScalarCoercion::toInt($frame[FrameKey::INDEX] ?? null, -1),
            ScalarCoercion::toString($frame[FrameKey::NS] ?? null),
            (bool) ($frame[FrameKey::OK] ?? false),
            ScalarCoercion::toString($frame[FrameKey::OUTPUT] ?? null),
            self::extractStringList($frame[FrameKey::FAILED_TESTS] ?? null),
            Counts::fromArray($rawCounts),
            WorkerOutcome::fromFrameValue($frame[FrameKey::OUTCOME] ?? null),
            (bool) ($frame[FrameKey::FOCUSED] ?? false),
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
            new Counts(error: 1, total: 1),
            WorkerOutcome::WorkerDied,
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
