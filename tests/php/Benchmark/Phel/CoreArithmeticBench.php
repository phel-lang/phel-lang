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
 * The three argument subjects exist because that count had no fixed arity and
 * fell into the tail, costing 13 to 17 times the two argument call (#3017).
 * Read each against its two argument sibling: they should sit within roughly
 * 2x, and a jump back to an order of magnitude means an arity stopped being
 * selected.
 *
 * {@see CoreBenchCase} for the conventions every subject here follows.
 *
 * @BeforeMethods("setUp")
 */
final class CoreArithmeticBench extends CoreBenchCase
{
    /** @var callable */
    private $even;

    /** @var callable */
    private $odd;

    /** @var callable */
    private $rem;

    /** @var callable */
    private $modulo;

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
    public function bench_add_three(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->add)($this->a, $this->b, $this->c);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_multiply_three(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->multiply)($this->a, $this->b, $this->c);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_max_three(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->max)($this->a, $this->b, $this->c);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_min_three(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->min)($this->a, $this->b, $this->c);
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

    /**
     * `even?` reaches its answer through `%` and `rem`, and `odd?` through
     * `even?` on top of that, so a three-call chain answers one modulo. These
     * subjects are paired against the raw PHP the int case compiles to.
     *
     * @Revs(1000)
     */
    public function bench_even(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->even)($i);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_even_raw(): void
    {
        $unused = false;
        for ($i = 0; $i < self::INNER; ++$i) {
            $unused = $i % 2 === 0;
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_odd(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->odd)($i);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_rem(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->rem)($i, 3);
        }
    }

    /**
     * `%` is an alias for `rem`. Read against the subject above: the gap is the
     * forwarding call and nothing else.
     *
     * @Revs(1000)
     */
    public function bench_modulo(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->modulo)($i, 3);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_rem_raw(): void
    {
        $unused = 0;
        for ($i = 0; $i < self::INNER; ++$i) {
            $unused = $i % 3;
        }
    }

    protected function setUpFixtures(): void
    {
        $this->even = $this->coreFn('even?');
        $this->odd = $this->coreFn('odd?');
        $this->rem = $this->coreFn('rem');
        $this->modulo = $this->coreFn('%');

        $this->inc = $this->coreFn('inc');
        $this->dec = $this->coreFn('dec');
        $this->max = $this->coreFn('max');
        $this->min = $this->coreFn('min');
        $this->add = $this->coreFn('+');
        $this->multiply = $this->coreFn('*');
    }
}
