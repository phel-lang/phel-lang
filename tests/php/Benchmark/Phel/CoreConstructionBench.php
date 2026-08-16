<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Phel;

use Phel;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Revs;

/**
 * The `phel.core` functions that build a value from loose arguments and were
 * given fixed arities for the small counts: `hash-set` (#3001), `str` (#2976),
 * and `atom` and `symbol` (#2973).
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

    /** @var callable */
    private $toArray;

    /** @var callable */
    private $atom;

    /** @var callable */
    private $symbol;

    private mixed $metaKeyword = null;

    private mixed $smallVector = null;

    private mixed $largeVector = null;

    private mixed $map = null;

    /** Typed `mixed` so `str` cannot see a string and skip its conversion. */
    private mixed $a = 'a';

    private mixed $b = 'b';

    private mixed $c = 'c';

    private mixed $d = 'd';

    private mixed $e = 'e';

    private mixed $f = 'f';

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
     * Four and five are fixed arities. They were the variadic tail until they
     * were not: 42.4μs to 10.9μs here, and 51.8μs to 12.7μs for five. The rest
     * argument and the `implode` were the whole difference, not the
     * concatenation. `src/phel` has 29 call sites at four arguments and 20 at
     * five, so these two guard the majority of real calls.
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
     * @Revs(1000)
     */
    public function bench_str_five(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->str)($this->a, $this->b, $this->c, $this->d, $this->e);
        }
    }

    /**
     * The variadic tail proper: six arguments still allocates a rest argument,
     * builds the intermediate array and `implode`s it. It is here so the arm
     * the fixed arities fall through to keeps a subject of its own, and
     * because widening them shortened its `more` from three entries to one,
     * worth 61.2μs to 45.6μs.
     *
     * @Revs(1000)
     */
    public function bench_str_six(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->str)($this->a, $this->b, $this->c, $this->d, $this->e, $this->f);
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

    /**
     * `to-array` over a sequential collection (#3021 A6). It used to be
     * `(apply php/array coll)`, which spread the collection into a variadic
     * call and rebuilt the array from the iterator; `SeqInterface::toArray`
     * does it directly.
     *
     * Two sizes, because the old cost grew with the collection and the new one
     * barely does: 1.08μs to 0.47μs at three elements, 10.61μs to 1.39μs at a
     * hundred, against a ~0.18μs empty-closure floor.
     *
     * The subject keeps its `to_php_array` name after the function was renamed
     * (#3076): the benchmark gate compares subjects by name against a stored
     * baseline, and renaming one drops it to "new subject, nothing to compare".
     *
     * @Revs(1000)
     */
    public function bench_to_php_array_small(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->toArray)($this->smallVector);
        }
    }

    /**
     * Called once per rev rather than folded into the `INNER` loop: the cost
     * already grows with the input, so the timer is negligible against it.
     *
     * @Revs(1000)
     */
    public function bench_to_php_array_large(): void
    {
        ($this->toArray)($this->largeVector);
    }

    /**
     * A map has no `toArray`, so it still goes through `apply`. Paired with the
     * two above it shows the branch is doing what it claims: this one should
     * not move.
     *
     * @Revs(1000)
     */
    public function bench_to_php_array_map(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->toArray)($this->map);
        }
    }

    /**
     * `(atom v)`, which is nearly every `atom` call. It used to allocate a rest
     * argument and `apply` `hash-map` over it to discover there were no
     * options.
     *
     * @Revs(1000)
     */
    public function bench_atom_plain(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->atom)(1);
        }
    }

    /**
     * The options path, still variadic. Paired with the one above so a change
     * that only moves the fast arity is told apart from one that moves both.
     *
     * @Revs(1000)
     */
    public function bench_atom_with_options(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->atom)(1, $this->metaKeyword, $this->map);
        }
    }

    /**
     * `symbol` used `[name-or-ns & [name]]`, the costliest variadic shape
     * measured in #2973: a rest argument built and then destructured back into
     * one value.
     *
     * @Revs(1000)
     */
    public function bench_symbol_one_argument(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->symbol)('foo');
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_symbol_two_arguments(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->symbol)('a', 'b');
        }
    }

    protected function setUpFixtures(): void
    {
        $this->hashSet = $this->coreFn('hash-set');
        $this->str = $this->coreFn('str');
        $this->atom = $this->coreFn('atom');
        $this->symbol = $this->coreFn('symbol');
        $this->metaKeyword = Phel::keyword('meta');

        $this->toArray = $this->coreFn('to-array');
        $this->smallVector = Phel::vector(['a', 'b', 'c']);
        $this->largeVector = Phel::vector(range(0, 99));
        $this->map = Phel::map(Phel::keyword('a'), 1, Phel::keyword('b'), 2);
    }
}
