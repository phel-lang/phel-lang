<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Phel;

use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Revs;

/**
 * The ordered comparison operators of `phel.core`: `<`, `<=`, `>` and `>=`.
 *
 * Two optimisations meet in these four. #2983 gave the operators fixed arities
 * and a `bool` return; #2984 put a native-int shortcut in the `lt2` family of
 * helpers underneath them, where the remaining time was. The helpers are
 * private, so the operator is the only way to reach them.
 *
 * Each operator is measured on three operand shapes, because they take
 * different paths and only the first one is shortcut:
 *
 * - **int/int** takes the shortcut and should sit near its `_raw` pair;
 * - **int/float** is excluded from it by `is_int` and falls through to PHP's
 *   own comparison, so it measures the cost the shortcut adds to operands that
 *   cannot use it;
 * - **ratio/int** routes through `NumericOperations/compare`, which the
 *   shortcut must never divert.
 *
 * Without the last two, a change that made ints fast by breaking the numeric
 * tower would look like a clean win here.
 *
 * {@see CoreBenchCase} for the conventions every subject here follows.
 *
 * @BeforeMethods("setUp")
 */
final class CoreComparisonBench extends CoreBenchCase
{
    /** @var callable */
    private $pos;

    /** @var callable */
    private $neg;

    /** @var callable */
    private $zero;

    /** @var callable */
    private $notEq;

    /** @var callable */
    private $lt;

    /** @var callable */
    private $lte;

    /** @var callable */
    private $gt;

    /** @var callable */
    private $gte;

    /** Typed `mixed` so the raw pairs are real work rather than a foldable literal. */
    private mixed $a = 7;

    private mixed $b = 11;

    private mixed $float = 11.5;

    private mixed $ratio = null;

    /**
     * @Revs(1000)
     */
    public function bench_lt_int_int(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->lt)($this->a, $this->b);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_lt_int_int_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            $unused = $this->a < $this->b;
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_lte_int_int(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->lte)($this->a, $this->b);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_gt_int_int(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->gt)($this->a, $this->b);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_gte_int_int(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->gte)($this->a, $this->b);
        }
    }

    /**
     * Three arguments reach the chained arity, which compares each pair through
     * the same helpers. A shortcut that only paid off on the two-argument form
     * would show up as this subject failing to move with the one above.
     *
     * @Revs(1000)
     */
    public function bench_lt_three_ints(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->lt)($this->a, $this->b, 19);
        }
    }

    /**
     * Excluded from the int shortcut by the float, so this is the operand shape
     * that pays for the shortcut without using it.
     *
     * @Revs(1000)
     */
    public function bench_lt_int_float(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->lt)($this->a, $this->float);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_lt_int_float_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            $unused = $this->a < $this->float;
        }
    }

    /**
     * The numeric tower path. It must stay flat: the shortcut is guarded so a
     * `Ratio` never reaches it.
     *
     * @Revs(1000)
     */
    public function bench_lt_ratio_int(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->lt)($this->ratio, $this->b);
        }
    }

    /**
     * `pos?` and `neg?` reach their answer through `>` and `<`, so a sign test
     * is two Phel calls. `zero?` is here as the control: it already goes
     * straight to `NumericOperations`, so it is what the other two can reach.
     *
     * @Revs(1000)
     */
    public function bench_pos(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->pos)($i);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_neg(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->neg)($i);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_zero(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->zero)($i);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_not_equals(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->notEq)($i, 1);
        }
    }

    protected function setUpFixtures(): void
    {
        $this->pos = $this->coreFn('pos?');
        $this->neg = $this->coreFn('neg?');
        $this->zero = $this->coreFn('zero?');
        $this->notEq = $this->coreFn('not=');

        $this->lt = $this->coreFn('<');
        $this->lte = $this->coreFn('<=');
        $this->gt = $this->coreFn('>');
        $this->gte = $this->coreFn('>=');
        $this->ratio = $this->coreFn('/')(1, 3);
    }
}
