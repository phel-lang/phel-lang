<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Phel;

use Phel\Lang\BigInt;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Revs;

/**
 * Binary `+`, `-` and `*` over operands the analyser could not type, which
 * the emitter lowers to the native operator behind an `is_int` guard with
 * the runtime call as the fallback (#3468).
 *
 * Floats and `BigInt`s fail the guard: `bench_add_untyped_float` and
 * `bench_add_untyped_bigint` measure what the guard costs the operands that
 * cannot use it, so a change that made ints fast by slowing the rest of the
 * numeric tower shows up there.
 *
 * {@see CoreBenchCase} for the conventions every subject here follows.
 *
 * @BeforeMethods("setUp")
 */
final class CoreGuardedArithmeticBench extends CoreBenchCase
{
    /** @var callable */
    private $addLoop;

    /** @var callable */
    private $subLoop;

    /** @var callable */
    private $mulLoop;

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
    public function bench_add_untyped(): void
    {
        ($this->addLoop)($this->a, $this->b, self::INNER);
    }

    /**
     * @Revs(1000)
     */
    public function bench_add_untyped_float(): void
    {
        ($this->addLoop)($this->fa, $this->fb, self::INNER);
    }

    /**
     * @Revs(1000)
     */
    public function bench_add_untyped_bigint(): void
    {
        ($this->addLoop)($this->ba, $this->bb, self::INNER);
    }

    /**
     * @Revs(1000)
     */
    public function bench_sub_untyped(): void
    {
        ($this->subLoop)($this->a, $this->b, self::INNER);
    }

    /**
     * @Revs(1000)
     */
    public function bench_mul_untyped(): void
    {
        ($this->mulLoop)($this->a, $this->b, self::INNER);
    }

    protected function setUpFixtures(): void
    {
        $this->ba = BigInt::fromInt(3);
        $this->bb = BigInt::fromInt(7);
        $this->addLoop = $this->compileBuildModeFn(
            '(fn [a b n] (loop [i 0 c nil] (if (php/< i n) (recur (php/+ i 1) (+ a b)) c)))',
        );
        $this->subLoop = $this->compileBuildModeFn(
            '(fn [a b n] (loop [i 0 c nil] (if (php/< i n) (recur (php/+ i 1) (- a b)) c)))',
        );
        $this->mulLoop = $this->compileBuildModeFn(
            '(fn [a b n] (loop [i 0 c nil] (if (php/< i n) (recur (php/+ i 1) (* a b)) c)))',
        );
    }
}
