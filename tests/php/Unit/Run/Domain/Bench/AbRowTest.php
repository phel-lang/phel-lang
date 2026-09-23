<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Domain\Bench;

use Phel\Run\Domain\Bench\AbRow;
use PHPUnit\Framework\TestCase;

final class AbRowTest extends TestCase
{
    public function test_the_delta_is_the_mean_of_the_per_pair_deltas(): void
    {
        // +10% and +30%: the mean of the deltas, not the delta of the means.
        $row = AbRow::fromPairs('ns/bench', [[100.0, 110.0], [200.0, 260.0]]);

        self::assertEqualsWithDelta(20.0, $row->meanDeltaPercent(), 0.0001);
        self::assertEqualsWithDelta(150.0, $row->meanA, 0.0001);
        self::assertEqualsWithDelta(185.0, $row->meanB, 0.0001);
    }

    public function test_every_pair_moving_the_same_way_is_not_noise(): void
    {
        $row = AbRow::fromPairs('ns/bench', [[100.0, 90.0], [100.0, 80.0], [100.0, 95.0]]);

        self::assertSame(3, $row->agreeingPairs());
        self::assertSame(3, $row->pairCount());
        self::assertFalse($row->isNoise());
    }

    public function test_mixed_signs_are_noise(): void
    {
        $row = AbRow::fromPairs('ns/bench', [[100.0, 104.0], [100.0, 97.0], [100.0, 103.0]]);

        self::assertSame(2, $row->agreeingPairs());
        self::assertTrue($row->isNoise());
    }

    public function test_the_agreement_counts_pairs_moving_with_the_mean_even_when_one_outlier_sets_it(): void
    {
        // Two pairs a little faster, one much slower: the mean says slower,
        // and only one pair agrees, which is what makes it read as noise.
        $row = AbRow::fromPairs('ns/bench', [[100.0, 99.0], [100.0, 98.0], [100.0, 130.0]]);

        self::assertGreaterThan(0.0, $row->meanDeltaPercent());
        self::assertSame(1, $row->agreeingPairs());
        self::assertTrue($row->isNoise());
    }

    public function test_a_single_pair_always_agrees_with_itself(): void
    {
        $row = AbRow::fromPairs('ns/bench', [[100.0, 120.0]]);

        self::assertSame(1, $row->agreeingPairs());
        self::assertFalse($row->isNoise());
    }

    public function test_a_pair_with_a_zero_duration_side_a_has_no_delta(): void
    {
        $row = AbRow::fromPairs('ns/bench', [[0.0, 5.0], [100.0, 110.0]]);

        self::assertSame(1, $row->pairCount());
        self::assertEqualsWithDelta(10.0, $row->meanDeltaPercent(), 0.0001);
    }

    public function test_it_is_slower_than_the_tolerance_only_when_every_pair_is(): void
    {
        $everyPair = AbRow::fromPairs('ns/bench', [[100.0, 115.0], [100.0, 112.0]]);
        $onePair = AbRow::fromPairs('ns/bench', [[100.0, 150.0], [100.0, 105.0]]);

        self::assertTrue($everyPair->isSlowerInEveryPairThan(10.0));
        self::assertFalse($everyPair->isSlowerInEveryPairThan(12.0));
        // A mean of +27.5% with one pair inside the tolerance is the machine, not the code.
        self::assertFalse($onePair->isSlowerInEveryPairThan(10.0));
    }

    public function test_a_faster_row_is_never_slower_than_the_tolerance(): void
    {
        $row = AbRow::fromPairs('ns/bench', [[100.0, 50.0], [100.0, 60.0]]);

        self::assertFalse($row->isSlowerInEveryPairThan(0.0));
    }

    public function test_a_row_without_pairs_is_neither_noise_nor_slower(): void
    {
        $row = AbRow::fromPairs('ns/bench', []);

        self::assertSame(0, $row->pairCount());
        self::assertFalse($row->isNoise());
        self::assertFalse($row->isSlowerInEveryPairThan(0.0));
    }
}
