<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Phel;

use Phel;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Revs;

use function count;
use function usort;

/**
 * The sequence functions of `phel.core` that carry an optimisation with no
 * benchmark guarding it: `sort` (#3003), `sort-by` (#3006), `interleave`
 * (#3002), `reduce` and `into` (#2977), `count` (#2969, #2971).
 *
 * Sizes are deliberately small. These subjects exist to catch a function that
 * grew a per-element or per-call cost it did not have, not to characterise
 * asymptotics, and a 32 element collection reaches every branch that a 32000
 * element one does while keeping the CI comparison run inside its budget.
 *
 * {@see CoreBenchCase} for the conventions every subject here follows.
 *
 * @BeforeMethods("setUp")
 */
final class CoreSeqBench extends CoreBenchCase
{
    private const int SIZE = 32;

    /** @var callable */
    private $sort;

    /** @var callable */
    private $sortBy;

    /** @var callable */
    private $identity;

    /** @var callable */
    private $interleave;

    /** @var callable */
    private $reduce;

    /** @var callable */
    private $into;

    /** @var callable */
    private $count;

    /** @var callable */
    private $add;

    /** @var callable */
    private $compare;

    /** @var list<int> */
    private array $intArray = [];

    /** @var list<string> */
    private array $stringArray = [];

    private mixed $ints = null;

    private mixed $strings = null;

    private mixed $keywords = null;

    private mixed $emptyVector = null;

    private mixed $map = null;

    /**
     * The all-int input, which is the shape `sort` can answer without calling
     * back into Phel per comparison. Paired against the PHP sort it is allowed
     * to become: a ratio near 1 means the wrapper is free, and a jump means the
     * fast path stopped being taken.
     *
     * @Revs(1000)
     */
    public function bench_sort_ints(): void
    {
        ($this->sort)($this->ints);
    }

    /**
     * @Revs(1000)
     */
    public function bench_sort_ints_raw(): void
    {
        $array = $this->intArray;
        usort($array, static fn(int $a, int $b): int => $a <=> $b);
        Phel::vector($array);
    }

    /**
     * The comparator path, which no fast path can serve. Kept alongside the int
     * subject on purpose: without it, a change that deletes the general path
     * and keeps only the specialised one still reads as an improvement.
     *
     * @Revs(1000)
     */
    public function bench_sort_strings(): void
    {
        ($this->sort)($this->strings);
    }

    /**
     * @Revs(1000)
     */
    public function bench_sort_strings_raw(): void
    {
        $array = $this->stringArray;
        usort($array, static fn(string $a, string $b): int => $a <=> $b);
        Phel::vector($array);
    }

    /**
     * An explicitly supplied comparator, which reaches `sort` through its two
     * argument arity and cannot be answered natively whatever the elements are.
     *
     * @Revs(1000)
     */
    public function bench_sort_explicit_comparator(): void
    {
        ($this->sort)($this->ints, $this->compare);
    }

    /**
     * `sort-by` computes each key once and sorts `[key value]` pairs (#3006).
     * What the transform removes is `keyfn` invocations, so the win scales with
     * what the key function costs and this subject is the floor: `identity` is
     * about as cheap as a key function gets, so anything the pair loses here is
     * the transform's own overhead rather than a saving it failed to make.
     *
     * @Revs(1000)
     */
    public function bench_sort_by_cheap_key(): void
    {
        ($this->sortBy)($this->identity, $this->ints);
    }

    /**
     * The same sort with a key function that actually does something, which is
     * the shape real code has. Read against `bench_sort_by_cheap_key`: the gap
     * between them is the per-key cost, and under the old comparator it was
     * paid about twice per comparison instead of once per element.
     *
     * @Revs(1000)
     */
    public function bench_sort_by_counting_key(): void
    {
        ($this->sortBy)($this->count, $this->strings);
    }

    /**
     * The explicit-comparator arity. It has to apply the comparator to the
     * keys rather than the values, so it takes a different path through the
     * transform than the two argument form above.
     *
     * @Revs(1000)
     */
    public function bench_sort_by_explicit_comparator(): void
    {
        ($this->sortBy)($this->count, $this->compare, $this->strings);
    }

    /**
     * `interleave` is lazy: it builds its seq array eagerly and returns a lazy
     * sequence over it. This subject measures only the eager prefix, which is
     * the part #3002 rewrote, and is the one that guards it.
     *
     * @Revs(1000)
     */
    public function bench_interleave_unrealized(): void
    {
        ($this->interleave)($this->keywords, $this->keywords);
    }

    /**
     * The same call, forced. Its reference is the subject above: the gap is the
     * generator producing six elements, which is the value `interleave` exists
     * to return rather than overhead to remove.
     *
     * @Revs(1000)
     */
    public function bench_interleave_realized(): void
    {
        ($this->count)(($this->interleave)($this->keywords, $this->keywords));
    }

    /**
     * @Revs(1000)
     */
    public function bench_reduce_sum(): void
    {
        ($this->reduce)($this->add, 0, $this->ints);
    }

    /**
     * The floor, not a target. It skips the `Volatile` accumulator and the
     * `reduced?` check per element that `reduce` owes its early termination
     * contract to, so the gap is a feature rather than waste.
     *
     * @Revs(1000)
     */
    public function bench_reduce_sum_raw(): void
    {
        $acc = 0;
        foreach ($this->intArray as $value) {
            $acc += $value;
        }
    }

    /**
     * The vector target, which builds through a transient rather than paying
     * copy-on-write per element.
     *
     * @Revs(1000)
     */
    public function bench_into_vector(): void
    {
        ($this->into)($this->emptyVector, $this->ints);
    }

    /**
     * @Revs(1000)
     */
    public function bench_into_vector_raw(): void
    {
        Phel::vector($this->intArray);
    }

    /**
     * @Revs(1000)
     */
    public function bench_count_vector(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->count)($this->ints);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_count_vector_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            $unused = count($this->ints);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_count_map(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->count)($this->map);
        }
    }

    /**
     * A string is not `Countable`, so it leaves the branch the two subjects
     * above take and reaches the string arm instead.
     *
     * @Revs(1000)
     */
    public function bench_count_string(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->count)('abcdefgh');
        }
    }

    protected function setUpFixtures(): void
    {
        $this->sort = $this->coreFn('sort');
        $this->sortBy = $this->coreFn('sort-by');
        $this->identity = $this->coreFn('identity');
        $this->interleave = $this->coreFn('interleave');
        $this->reduce = $this->coreFn('reduce');
        $this->into = $this->coreFn('into');
        $this->count = $this->coreFn('count');
        $this->add = $this->coreFn('+');
        $this->compare = $this->coreFn('compare');

        // A fixed shuffle, not `shuffle()`: a benchmark whose input changes
        // between the baseline run and the comparison run measures the input.
        for ($i = 0; $i < self::SIZE; ++$i) {
            $this->intArray[] = ($i * 17) % self::SIZE;
            $this->stringArray[] = 'k' . (($i * 17) % self::SIZE);
        }

        $this->ints = Phel::vector($this->intArray);
        $this->strings = Phel::vector($this->stringArray);
        $this->keywords = Phel::vector([
            Phel::keyword('a'), Phel::keyword('b'), Phel::keyword('c'),
        ]);
        $this->emptyVector = Phel::vector([]);
        $this->map = Phel::map(Phel::keyword('a'), 1, Phel::keyword('b'), 2);
    }
}
