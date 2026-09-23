<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Domain\Bench;

use Phel\Run\Domain\Bench\AbComparison;
use Phel\Run\Domain\Bench\AbReport;
use Phel\Run\Domain\Bench\AbRow;
use PHPUnit\Framework\TestCase;

use function array_map;

final class AbComparisonTest extends TestCase
{
    public function test_it_pairs_each_benchmark_across_runs(): void
    {
        $comparison = new AbComparison();
        $comparison->addPair(['ns/a' => 100.0, 'ns/b' => 10.0], ['ns/a' => 90.0, 'ns/b' => 11.0]);
        $comparison->addPair(['ns/a' => 100.0, 'ns/b' => 10.0], ['ns/a' => 80.0, 'ns/b' => 9.0]);

        $rows = $comparison->rows();

        self::assertSame(['ns/a', 'ns/b'], array_map(static fn(AbRow $row): string => $row->name, $rows));
        self::assertSame(2, $rows[0]->pairCount());
        self::assertFalse($rows[0]->isNoise());
        self::assertTrue($rows[1]->isNoise());
    }

    public function test_a_benchmark_on_one_side_only_is_not_a_row(): void
    {
        $comparison = new AbComparison();
        $comparison->addPair(['ns/both' => 1.0, 'ns/removed' => 1.0], ['ns/both' => 1.0, 'ns/added' => 1.0]);

        self::assertCount(1, $comparison->rows());
        self::assertSame(['ns/added'], $comparison->onlyInB());
        self::assertSame(['ns/removed'], $comparison->onlyInA());
    }

    public function test_regressions_are_the_rows_slower_than_the_tolerance_in_every_pair(): void
    {
        $comparison = new AbComparison();
        $comparison->addPair(['ns/slow' => 100.0, 'ns/flaky' => 100.0], ['ns/slow' => 120.0, 'ns/flaky' => 150.0]);
        $comparison->addPair(['ns/slow' => 100.0, 'ns/flaky' => 100.0], ['ns/slow' => 125.0, 'ns/flaky' => 101.0]);

        $regressions = $comparison->regressions(10.0);

        self::assertSame(['ns/slow'], array_map(static fn(AbRow $row): string => $row->name, $regressions));
    }

    public function test_nothing_measured_is_empty(): void
    {
        $comparison = new AbComparison();
        $comparison->addPair([], []);

        self::assertTrue($comparison->isEmpty());
    }

    public function test_the_report_labels_noise_and_one_sided_rows(): void
    {
        $comparison = new AbComparison();
        $comparison->addPair(['ns/steady' => 2000.0, 'ns/flaky' => 100.0], ['ns/steady' => 1000.0, 'ns/flaky' => 110.0, 'ns/added' => 5.0]);
        $comparison->addPair(['ns/steady' => 2000.0, 'ns/flaky' => 100.0], ['ns/steady' => 1000.0, 'ns/flaky' => 95.0, 'ns/added' => 5.0]);

        $lines = new AbReport()->render($comparison);

        self::assertMatchesRegularExpression('/^benchmark\s+a-mean\s+b-mean\s+delta\s+signs$/', $lines[0]);
        self::assertMatchesRegularExpression('#^ns/steady\s+2\.000μs\s+1\.000μs\s+-50\.00%\s+2/2$#u', $lines[1]);
        self::assertMatchesRegularExpression('#^ns/flaky\s+100\.000ns\s+102\.500ns\s+\+2\.50%\s+1/2\s+noise$#u', $lines[2]);
        self::assertMatchesRegularExpression('#^ns/added\s+-\s+new$#', $lines[3]);
    }
}
