<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Phel;

use Phel\Lang\BigInt;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Revs;

/**
 * Comparisons, equality and `inc` over operands the analyser could not type,
 * which the emitter lowers to a native expression behind an `is_*` guard
 * with the runtime call as the fallback (#3351).
 *
 * The saving lives in the emitted code, so each subject compiles a Phel `fn`
 * in build mode and loops inside it; the `_raw` twins are the PHP the int
 * case comes down to. Floats pass the ordering guard (`is_scalar`), so
 * `bench_lt_untyped_float` should sit next to the int subject.
 * `bench_lt_untyped_bigint` feeds `BigInt`s, which fail it: it measures what
 * the guard costs the operands that cannot use it, so a change that made
 * scalars fast by slowing the numeric tower shows up here.
 *
 * {@see CoreBenchCase} for the conventions every subject here follows.
 *
 * @BeforeMethods("setUp")
 */
final class CoreGuardedComparisonBench extends CoreBenchCase
{
    /** @var callable */
    private $ltLoop;

    /** @var callable */
    private $eqLoop;

    /** @var callable */
    private $incLoop;

    /** Typed `mixed` so the operands reach the fn untyped. */
    private mixed $a = 3;

    private mixed $b = 7;

    private mixed $fa = 3.5;

    private mixed $fb = 7.5;

    private mixed $ba = null;

    private mixed $bb = null;

    /**
     * @Revs(1000)
     */
    public function bench_lt_untyped(): void
    {
        ($this->ltLoop)($this->a, $this->b, self::INNER);
    }

    /**
     * @Revs(1000)
     */
    public function bench_lt_untyped_float(): void
    {
        ($this->ltLoop)($this->fa, $this->fb, self::INNER);
    }

    /**
     * @Revs(1000)
     */
    public function bench_lt_untyped_bigint(): void
    {
        ($this->ltLoop)($this->ba, $this->bb, self::INNER);
    }

    /**
     * @Revs(1000)
     */
    public function bench_lt_untyped_raw(): void
    {
        $a = $this->a;
        $b = $this->b;
        $c = 0;
        for ($i = 0; $i < self::INNER; ++$i) {
            if ($a < $b) {
                ++$c;
            }
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_eq_literal_untyped(): void
    {
        ($this->eqLoop)($this->a, self::INNER);
    }

    /**
     * @Revs(1000)
     */
    public function bench_eq_literal_untyped_raw(): void
    {
        $a = $this->a;
        $c = 0;
        for ($i = 0; $i < self::INNER; ++$i) {
            if ($a === 3) {
                ++$c;
            }
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_inc_untyped(): void
    {
        ($this->incLoop)($this->a, self::INNER);
    }

    /**
     * @Revs(1000)
     */
    public function bench_inc_untyped_raw(): void
    {
        $x = $this->a;
        for ($i = 0; $i < self::INNER; ++$i) {
            ++$x;
        }
    }

    protected function setUpFixtures(): void
    {
        $this->ba = BigInt::fromInt(3);
        $this->bb = BigInt::fromInt(7);
        $this->ltLoop = $this->compileBuildModeFn(
            '(fn [a b n] (loop [i 0 c 0] (if (php/< i n) (recur (php/+ i 1) (if (< a b) (php/+ c 1) c)) c)))',
        );
        $this->eqLoop = $this->compileBuildModeFn(
            '(fn [a n] (loop [i 0 c 0] (if (php/< i n) (recur (php/+ i 1) (if (= a 3) (php/+ c 1) c)) c)))',
        );
        $this->incLoop = $this->compileBuildModeFn(
            '(fn [a n] (loop [i 0 x a] (if (php/< i n) (recur (php/+ i 1) (inc x)) x)))',
        );
    }
}
