<?php

declare(strict_types=1);

namespace Phel\Run\Application\Test;

use function is_int;
use function max;

/**
 * Per-namespace test counters, plus an aggregator that sums them
 * across the whole run. Plain value object; no behaviour beyond
 * arithmetic so the orchestrator never reaches into a raw
 * `array<string, int>` dictionary.
 */
final class Counts
{
    public function __construct(
        public int $pass = 0,
        public int $failed = 0,
        public int $error = 0,
        public int $skipped = 0,
        public int $total = 0,
    ) {}

    /**
     * @param array<string, mixed> $raw
     */
    public static function fromArray(array $raw): self
    {
        return new self(
            self::pickInt($raw, FrameKey::COUNT_PASS),
            self::pickInt($raw, FrameKey::COUNT_FAILED),
            self::pickInt($raw, FrameKey::COUNT_ERROR),
            self::pickInt($raw, FrameKey::COUNT_SKIPPED),
            self::pickInt($raw, FrameKey::COUNT_TOTAL),
        );
    }

    public function add(self $other): void
    {
        $this->pass += $other->pass;
        $this->failed += $other->failed;
        $this->error += $other->error;
        $this->skipped += $other->skipped;
        $this->total += $other->total;
    }

    public function hasFailures(): bool
    {
        return $this->failed > 0 || $this->error > 0;
    }

    /**
     * @param array<string, mixed> $raw
     */
    private static function pickInt(array $raw, string $key): int
    {
        $value = $raw[$key] ?? 0;
        if (is_int($value)) {
            return max(0, $value);
        }

        if (is_numeric($value)) {
            return max(0, (int) $value);
        }

        return 0;
    }
}
