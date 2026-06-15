<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Lang;

use Phel\Lang\NumericOperations;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\Revs;

/**
 * Native-int arithmetic dispatch micro-benchmark.
 *
 * The compiler routes every Phel `+ - * < =` on numbers through
 * {@see NumericOperations}. The overwhelmingly common runtime case is two
 * native PHP ints (loop counters, indexes, small sums), which the dispatch
 * ladders reach only after every `is_float`/`instanceof` check fails. This
 * measures that hot path.
 */
final class NumericOperationsBench
{
    /**
     * @Revs(50000)
     *
     * @Iterations(5)
     */
    public function bench_int_add(): void
    {
        for ($i = 0; $i < 32; ++$i) {
            NumericOperations::add($i, 7);
        }
    }

    /**
     * @Revs(50000)
     *
     * @Iterations(5)
     */
    public function bench_int_compare(): void
    {
        for ($i = 0; $i < 32; ++$i) {
            NumericOperations::compare($i, 16);
        }
    }

    /**
     * @Revs(50000)
     *
     * @Iterations(5)
     */
    public function bench_int_is_equal(): void
    {
        for ($i = 0; $i < 32; ++$i) {
            NumericOperations::isEqual($i, 16);
        }
    }

    /**
     * @Revs(50000)
     *
     * @Iterations(5)
     */
    public function bench_int_multiply(): void
    {
        for ($i = 0; $i < 32; ++$i) {
            NumericOperations::multiply($i, 3);
        }
    }
}
