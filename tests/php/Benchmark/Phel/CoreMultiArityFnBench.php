<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Phel;

use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Revs;

/**
 * Multi-arity fn values created at runtime, the shape `comp` and `partial`
 * hand back: a four-arity fn per call (#3355).
 *
 * Each arity used to be a `Closure` built in the class constructor, so every
 * `(comp f g)` or `(partial f x)` allocated four closures before it was ever
 * called. The arities are methods now. The `create_and_call` subjects pay
 * one creation and one call, the way `(map (comp f g) xs)` or a `partial`
 * built inside a loop does; `bench_call_created` calls a value made once,
 * through `__invoke`, which is what a caller holding the fn in a local does.
 *
 * {@see CoreBenchCase} for the conventions every subject here follows.
 *
 * @BeforeMethods("setUp")
 */
final class CoreMultiArityFnBench extends CoreBenchCase
{
    /** @var callable */
    private $comp;

    /** @var callable */
    private $partial;

    /** @var callable */
    private $inc;

    /** @var callable */
    private $plus;

    /** @var callable */
    private $composed;

    /**
     * @Revs(1000)
     */
    public function bench_comp_create_and_call(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            $unused = (($this->comp)($this->inc, $this->inc))($i);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_partial_create_and_call(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            $unused = (($this->partial)($this->plus, 1))($i);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_call_created(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            $unused = ($this->composed)($i);
        }
    }

    /**
     * The floor for `bench_call_created`: the two `inc` calls it composes.
     *
     * @Revs(1000)
     */
    public function bench_call_created_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            $unused = ($this->inc)(($this->inc)($i));
        }
    }

    protected function setUpFixtures(): void
    {
        $this->comp = $this->coreFn('comp');
        $this->partial = $this->coreFn('partial');
        $this->inc = $this->coreFn('inc');
        $this->plus = $this->coreFn('+');
        $this->composed = ($this->comp)($this->inc, $this->inc);
    }
}
