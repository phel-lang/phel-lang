<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Phel;

use Phel\Build\BuildFacade;
use Phel\Run\RunFacade;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Revs;
use RuntimeException;

use function is_callable;

/**
 * Build-mode calls to a variadic multi-arity core fn at one of its fixed
 * arities: `(+ acc x)`, `(conj v x)`, `(get m k nf)` (#3354).
 *
 * Their call slot now holds an `AbstractFn` (`\Phel::fnSlot`), so the site
 * calls `invokeArityN` directly. Before, a variadic multi-arity callee was
 * kept off the arity shortcut, because the shortcut wrote its arguments in
 * both arms of an `instanceof` ternary and nesting it blew up the code, and
 * each call went through `__invoke(...$args)` and its `match`.
 *
 * The saving lives in the emitted code, so each subject compiles a Phel `fn`
 * in build mode and loops inside it.
 *
 * {@see CoreBenchCase} for the conventions every subject here follows.
 *
 * @BeforeMethods("setUp")
 */
final class CoreCallSlotBench extends CoreBenchCase
{
    /** @var callable */
    private $plusLoop;

    /** @var callable */
    private $conjLoop;

    /** @var callable */
    private $getLoop;

    /** Typed `mixed` so the operands reach the fn untyped. */
    private mixed $x = 3;

    /**
     * @Revs(1000)
     */
    public function bench_plus_fixed_arity(): void
    {
        ($this->plusLoop)($this->x, self::INNER);
    }

    /**
     * @Revs(1000)
     */
    public function bench_conj_fixed_arity(): void
    {
        ($this->conjLoop)($this->x, self::INNER);
    }

    /**
     * @Revs(1000)
     */
    public function bench_get_fixed_arity(): void
    {
        ($this->getLoop)($this->x, self::INNER);
    }

    protected function setUpFixtures(): void
    {
        $this->plusLoop = $this->compileInBuildMode(
            '(fn [x n] (loop [i 0 acc x] (if (php/< i n) (recur (php/+ i 1) (+ acc x)) acc)))',
        );
        $this->conjLoop = $this->compileInBuildMode(
            '(fn [x n] (loop [i 0 v (list)] (if (php/< i n) (recur (php/+ i 1) (conj v x)) v)))',
        );
        $this->getLoop = $this->compileInBuildMode(
            '(fn [x n] (let [m {:a x}] (loop [i 0 acc nil] (if (php/< i n) (recur (php/+ i 1) (get m :a acc)) acc))))',
        );
    }

    private function compileInBuildMode(string $phelCode): callable
    {
        BuildFacade::enableBuildMode();
        try {
            $fn = new RunFacade()->eval($phelCode);
        } finally {
            BuildFacade::disableBuildMode();
        }

        if (!is_callable($fn)) {
            throw new RuntimeException($phelCode . ' did not evaluate to a callable');
        }

        return $fn;
    }
}
