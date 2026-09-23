<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Bench;

use function array_sum;
use function count;

/**
 * One benchmark measured on both sides of an interleaved A/B run: side A is
 * the git ref, side B the working tree. Each pair yields one delta, and the
 * verdict comes from how the deltas agree, never from one run alone: a
 * machine warming up between two runs moves the mean of both sides of a
 * pair together, but not the sign of their difference.
 *
 * @internal
 */
final readonly class AbRow
{
    /**
     * @param list<float> $deltas per-pair `(B - A) / A`, in percent
     */
    private function __construct(
        public string $name,
        public float $meanA,
        public float $meanB,
        private array $deltas,
    ) {}

    /**
     * @param list<array{0: float, 1: float}> $pairs mean nanoseconds of side A and side B, one entry per pair
     */
    public static function fromPairs(string $name, array $pairs): self
    {
        $sumA = 0.0;
        $sumB = 0.0;
        $deltas = [];
        foreach ($pairs as [$a, $b]) {
            $sumA += $a;
            $sumB += $b;
            // A zero-duration side A has no ratio to report.
            if ($a > 0.0) {
                $deltas[] = 100.0 * ($b - $a) / $a;
            }
        }

        $count = count($pairs);

        return new self(
            $name,
            $count === 0 ? 0.0 : $sumA / $count,
            $count === 0 ? 0.0 : $sumB / $count,
            $deltas,
        );
    }

    public function pairCount(): int
    {
        return count($this->deltas);
    }

    public function meanDeltaPercent(): float
    {
        return $this->deltas === [] ? 0.0 : array_sum($this->deltas) / count($this->deltas);
    }

    /**
     * How many pairs moved in the direction of the mean delta.
     */
    public function agreeingPairs(): int
    {
        $sign = $this->meanDeltaPercent() <=> 0.0;
        $agreeing = 0;
        foreach ($this->deltas as $delta) {
            if (($delta <=> 0.0) === $sign) {
                ++$agreeing;
            }
        }

        return $agreeing;
    }

    public function isNoise(): bool
    {
        return $this->deltas !== [] && $this->agreeingPairs() < count($this->deltas);
    }

    /**
     * True only when every pair is slower by more than `$tolerance` percent:
     * one slow pair is the machine, every pair is the code.
     */
    public function isSlowerInEveryPairThan(float $tolerance): bool
    {
        if ($this->deltas === []) {
            return false;
        }

        foreach ($this->deltas as $delta) {
            if ($delta <= $tolerance) {
                return false;
            }
        }

        return true;
    }
}
