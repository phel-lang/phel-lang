<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Phel;

use Phel;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Revs;

/**
 * The `phel.core` functions that build a value from loose arguments and were
 * given fixed arities for the small counts: `hash-set` (#3001) and `str`
 * (#2976).
 *
 * Both were optimised by removing the rest argument from the counts that
 * actually occur, so each is measured at a count inside the fixed arities and
 * at one past them, on the variadic tail. Measuring only one of the two hides
 * half of what changed.
 *
 * `str` carries a second trap: a call whose arguments are string-typed never
 * reaches the function at all, because `CoreFnCallEmitter::tryEmitStrConcat`
 * lowers it to native `.` at compile time. These subjects call the function
 * through a resolved callable, so they measure the untagged path, which is the
 * one a value from a parameter or a global reaches. A benchmark written as Phel
 * source with string literals would measure the emitter instead and stay flat
 * whatever happens to `str`.
 *
 * {@see CoreBenchCase} for the conventions every subject here follows.
 *
 * @BeforeMethods("setUp")
 */
final class CoreConstructionBench extends CoreBenchCase
{
    /** @var callable */
    private $hashSet;

    /** @var callable */
    private $str;

    /** Typed `mixed` so `str` cannot see a string and skip its conversion. */
    private mixed $a = 'a';

    private mixed $b = 'b';

    private mixed $c = 'c';

    private mixed $d = 'd';

    private mixed $number = 42;

    /**
     * @Revs(1000)
     */
    public function bench_hash_set_three(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->hashSet)($this->a, $this->b, $this->c);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_hash_set_three_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            $unused = Phel::set([$this->a, $this->b, $this->c]);
        }
    }

    /**
     * One argument past the fixed arities, so the rest argument and the `apply`
     * are back. Its reference is the subject above: the gap is what the fixed
     * arities buy.
     *
     * @Revs(1000)
     */
    public function bench_hash_set_four(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->hashSet)($this->a, $this->b, $this->c, $this->d);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_str_two(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->str)($this->a, $this->b);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_str_two_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            $unused = $this->a . $this->b;
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_str_three(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->str)($this->a, $this->b, $this->c);
        }
    }

    /**
     * The variadic tail: four arguments builds the intermediate array and
     * `implode`s it, which the three argument arity does not.
     *
     * @Revs(1000)
     */
    public function bench_str_four(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->str)($this->a, $this->b, $this->c, $this->d);
        }
    }

    /**
     * A non-string argument, which reaches `val-to-str` with real work to do
     * rather than returning its input.
     *
     * @Revs(1000)
     */
    public function bench_str_number(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->str)($this->number);
        }
    }

    protected function setUpFixtures(): void
    {
        $this->hashSet = $this->coreFn('hash-set');
        $this->str = $this->coreFn('str');
    }
}
