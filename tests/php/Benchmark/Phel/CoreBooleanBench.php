<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Phel;

use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Revs;

use function is_int;

/**
 * The boolean operators of `phel.core` that carry an emitter optimisation with
 * no benchmark guarding it: `not` over an operand already known to be a PHP
 * bool (#3021 C5).
 *
 * The pair brackets what that specialisation removes: `bench_not_over_bool`
 * invokes `phel.core/not`, `bench_not_over_bool_raw` is the native `!` the
 * emitter now writes instead. Measured 3.7x here (0.751μs against 0.203μs).
 *
 * That ratio is a lower bound, not the whole saving. `coreFn()` resolves the
 * callable once in `setUp`, so the subject pays the invoke but not the registry
 * lookup a cold call site pays, and in a test slot the change also drops the
 * `Truthy` adapter (see below).
 *
 * Not covered: the same call in an `if` test slot, where registering `not` as
 * bool-returning also drops the `Truthy` adapter. That saving lives in
 * `IfEmitter` and cannot be reached through a callable, so it is pinned by the
 * `Call/not-over-bool-operand-in-test-slot.test` fixture instead.
 *
 * The `untyped`, `bool_param` and `local` subjects measure emitted code,
 * compiled in build mode: `(not x)` over an untyped operand is inlined as a
 * nil/false probe, an `if` over a `^bool` param skips the truthiness check,
 * and an `if` over any other local checks it without the `$__truthy`
 * temporary (#3352, #3353). Each loops inside the compiled fn so the saving
 * is not drowned by the call into it; the `_raw` twins are the PHP it comes
 * down to.
 *
 * {@see CoreBenchCase} for the conventions every subject here follows.
 *
 * @BeforeMethods("setUp")
 */
final class CoreBooleanBench extends CoreBenchCase
{
    /** @var callable */
    private $not;

    /** @var callable */
    private $notUntypedLoop;

    /** @var callable */
    private $boolParamLoop;

    /** @var callable */
    private $localLoop;

    /** Typed `mixed` so the raw pair is real work rather than a foldable literal. */
    private mixed $a = 7;

    /**
     * @Revs(1000)
     */
    public function bench_not_over_bool(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->not)(is_int($this->a));
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_not_over_bool_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            $unused = !is_int($this->a);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_not_untyped(): void
    {
        ($this->notUntypedLoop)($this->a, self::INNER);
    }

    /**
     * @Revs(1000)
     */
    public function bench_not_untyped_raw(): void
    {
        $x = $this->a;
        $c = 0;
        for ($i = 0; $i < self::INNER; ++$i) {
            if ($x === null || $x === false) {
                ++$c;
            }
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_if_bool_param(): void
    {
        ($this->boolParamLoop)(is_int($this->a), self::INNER);
    }

    /**
     * @Revs(1000)
     */
    public function bench_if_bool_param_raw(): void
    {
        $b = is_int($this->a);
        $c = 0;
        for ($i = 0; $i < self::INNER; ++$i) {
            if ($b) {
                ++$c;
            }
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_if_local(): void
    {
        ($this->localLoop)($this->a, self::INNER);
    }

    /**
     * @Revs(1000)
     */
    public function bench_if_local_raw(): void
    {
        $x = $this->a;
        $c = 0;
        for ($i = 0; $i < self::INNER; ++$i) {
            if ($x !== null && $x !== false) {
                ++$c;
            }
        }
    }

    protected function setUpFixtures(): void
    {
        $this->not = $this->coreFn('not');
        $this->notUntypedLoop = $this->compileBuildModeFn(
            '(fn [x n] (loop [i 0 c 0] (if (php/< i n) (recur (php/+ i 1) (if (not x) (php/+ c 1) c)) c)))',
        );
        $this->boolParamLoop = $this->compileBuildModeFn(
            '(fn [^bool b n] (loop [i 0 c 0] (if (php/< i n) (recur (php/+ i 1) (if b (php/+ c 1) c)) c)))',
        );
        $this->localLoop = $this->compileBuildModeFn(
            '(fn [x n] (loop [i 0 c 0] (if (php/< i n) (recur (php/+ i 1) (if x (php/+ c 1) c)) c)))',
        );
    }
}
