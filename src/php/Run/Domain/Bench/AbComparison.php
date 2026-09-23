<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Bench;

use function array_filter;
use function array_keys;
use function array_values;

/**
 * Accumulates the pairs of an interleaved A/B run, keyed by benchmark
 * (`ns/name`), and turns them into rows. Rows keep the order in which side B
 * first reported them.
 *
 * @internal
 */
final class AbComparison
{
    /** @var array<string, list<array{0: float, 1: float}>> */
    private array $pairs = [];

    /** @var array<string, true> */
    private array $seenInA = [];

    /** @var array<string, true> */
    private array $seenInB = [];

    /**
     * @param array<string, float> $sideA mean nanoseconds per benchmark of one side A run
     * @param array<string, float> $sideB mean nanoseconds per benchmark of the side B run that followed it
     */
    public function addPair(array $sideA, array $sideB): void
    {
        foreach ($sideB as $name => $meanB) {
            $this->seenInB[$name] = true;
            $this->pairs[$name] ??= [];
            if (isset($sideA[$name])) {
                $this->pairs[$name][] = [$sideA[$name], $meanB];
            }
        }

        foreach (array_keys($sideA) as $name) {
            $this->seenInA[$name] = true;
        }
    }

    public function isEmpty(): bool
    {
        return $this->seenInA === [] && $this->seenInB === [];
    }

    /**
     * @return list<AbRow> benchmarks measured on both sides
     */
    public function rows(): array
    {
        $rows = [];
        foreach ($this->pairs as $name => $pairs) {
            if ($pairs !== []) {
                $rows[] = AbRow::fromPairs($name, $pairs);
            }
        }

        return $rows;
    }

    /**
     * @return list<string> benchmarks the working tree has and the ref does not
     */
    public function onlyInB(): array
    {
        return array_values(array_filter(
            array_keys($this->seenInB),
            fn(string $name): bool => !isset($this->seenInA[$name]),
        ));
    }

    /**
     * @return list<string> benchmarks the ref has and the working tree does not
     */
    public function onlyInA(): array
    {
        return array_values(array_filter(
            array_keys($this->seenInA),
            fn(string $name): bool => !isset($this->seenInB[$name]),
        ));
    }

    /**
     * @return list<AbRow> rows slower than side A by more than `$tolerance` percent in every pair
     */
    public function regressions(float $tolerance): array
    {
        return array_values(array_filter(
            $this->rows(),
            static fn(AbRow $row): bool => $row->isSlowerInEveryPairThan($tolerance),
        ));
    }
}
