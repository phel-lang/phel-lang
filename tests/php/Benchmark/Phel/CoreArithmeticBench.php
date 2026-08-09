<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Phel;

use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Revs;

/**
 * The arithmetic functions of `phel.core` whose optimisation is a call shape
 * rather than a calculation: fixed arities on `+`, `*` and `-` (#2982, #2973),
 * on `max` and `min` (#2992), and the inlined nil guard in `inc` and `dec`
 * (#2990).
 *
 * {@see \PhelTest\Benchmark\Lang\NumericOperationsBench} measures the layer
 * below these functions, so it stays flat when the wrapper regresses, which is
 * exactly the case these subjects exist to catch.
 *
 * Every subject is measured on its two argument form *and*, where the function
 * has one, on the variadic tail. A fixed arity that stops being selected is
 * invisible if only the tail is measured, and a tail that regresses is
 * invisible if only the fixed arity is.
 *
 * {@see CoreBenchCase} for the conventions every subject here follows.
 *
 * @BeforeMethods("setUp")
 */
final class CoreArithmeticBench extends CoreBenchCase
{
    /** @var callable */
    private $inc;

    /** @var callable */
    private $dec;

    /** @var callable */
    private $max;

    /** @var callable */
    private $min;

    /** @var callable */
    private $add;

    /** @var callable */
    private $multiply;

    /** Typed `mixed` so the raw pairs are real work rather than a foldable literal. */
    private mixed $a = 7;

    private mixed $b = 11;

    private mixed $c = 3;

    private mixed $d = 19;

    /**
     * @Revs(1000)
     */
    public function bench_inc(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->inc)($this->a);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_inc_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            $unused = $this->a + 1;
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_dec(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->dec)($this->a);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_dec_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            $unused = $this->a - 1;
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_add_two(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->add)($this->a, $this->b);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_add_two_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            $unused = $this->a + $this->b;
        }
    }

    /**
     * The variadic tail, which is a different function body from the subject
     * above rather than the same one called with more arguments.
     *
     * @Revs(1000)
     */
    public function bench_add_four(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->add)($this->a, $this->b, $this->c, $this->d);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_add_four_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            $unused = $this->a + $this->b + $this->c + $this->d;
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_multiply_two(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->multiply)($this->a, $this->b);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_max_two(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->max)($this->a, $this->b);
        }
    }

    /**
     * The NaN guard `max` and `min` owe their contract to is two `is_float`
     * checks the raw comparison does not make, so the gap is the contract.
     *
     * @Revs(1000)
     */
    public function bench_max_two_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            $unused = $this->a > $this->b ? $this->a : $this->b;
        }
    }

    /**
     * The variadic tail, which folds through the pair arity per element.
     *
     * @Revs(1000)
     */
    public function bench_max_four(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->max)($this->a, $this->b, $this->c, $this->d);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_min_two(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->min)($this->a, $this->b);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_min_four(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->min)($this->a, $this->b, $this->c, $this->d);
        }
    }

    protected function setUpFixtures(): void
    {
        $this->inc = $this->coreFn('inc');
        $this->dec = $this->coreFn('dec');
        $this->max = $this->coreFn('max');
        $this->min = $this->coreFn('min');
        $this->add = $this->coreFn('+');
        $this->multiply = $this->coreFn('*');
    }
}
