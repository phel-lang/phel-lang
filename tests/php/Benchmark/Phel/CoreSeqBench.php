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
 * (#3002), `reduce` and `into` (#2977), `count` (#2969, #2971), `map` and
 * `filter` (#3021).
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
    private $mapFn;

    /** @var callable */
    private $filterFn;

    /** @var callable */
    private $inc;

    /** @var callable */
    private $isEven;

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

    /** @var callable */
    private $partitionBy;

    /** @var callable */
    private $dedupe;

    /** @var callable */
    private $transduce;

    /** @var callable */
    private $vec;

    /** @var callable */
    private $merge;

    /** @var callable */
    private $mergeWith;

    /** @var callable */
    private $mapInvert;

    /** @var callable */
    private $updateVals;

    /** @var callable */
    private $updateKeys;

    /** @var callable */
    private $renameKeys;

    /** @var callable */
    private $kvs;

    /** @var callable */
    private $phpToPhel;

    /** @var callable */
    private $zipmap;

    /** @var callable */
    private $frequencies;

    /** @var callable */
    private $groupBy;

    /** @var callable */
    private $notEven;

    /** @var callable */
    private $always;

    /** @var callable */
    private $someFn;

    /** @var callable */
    private $everyPred;

    /** @var callable */
    private $slice;

    /** @var callable */
    private $take;

    /** @var callable */
    private $distinct;

    /** @var callable */
    private $concat;

    /** @var callable */
    private $dissoc;

    /** @var callable */
    private $numEquals;

    /** @var callable */
    private $comp;

    private mixed $dissocKey = null;

    private mixed $vector = null;

    /** @var list<int> */
    private array $intArray = [];

    /** @var list<string> */
    private array $stringArray = [];

    private mixed $ints = null;

    private mixed $strings = null;

    private mixed $keywords = null;

    private mixed $emptyVector = null;

    private mixed $map = null;

    private mixed $emptyMap = null;

    /** @var callable */
    private $invert;

    /** @var callable */
    private $selectKeys;

    /** @var callable */
    private $setFn;

    private mixed $bigMapAKeys = null;

    private mixed $bigMapA = null;

    private mixed $bigMapB = null;

    private mixed $pairs = null;

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
     * `map` and `filter` are the two most-called sequence functions, and both
     * were `[f & args]` plus a `case` on the rest count (#3021). These subjects
     * measure the call and the lazy-seq construction, not the realization, so a
     * regression in the dispatch is not hidden behind the elements.
     *
     * @Revs(1000)
     */
    public function bench_map_unrealized(): void
    {
        ($this->mapFn)($this->inc, $this->ints);
    }

    /**
     * @Revs(1000)
     */
    public function bench_filter_unrealized(): void
    {
        ($this->filterFn)($this->isEven, $this->ints);
    }

    /**
     * The one-argument form returns a transducer and touches no collection at
     * all, so it is almost entirely the call shape this change is about.
     *
     * @Revs(1000)
     */
    public function bench_map_transducer(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->mapFn)($this->inc);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_filter_transducer(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->filterFn)($this->isEven);
        }
    }

    /**
     * The multi-collection tail, which keeps its rest argument because there it
     * is a real case rather than an error. Kept so that speeding the fixed
     * arities up by regressing the tail cannot read as a clean win.
     *
     * @Revs(1000)
     */
    public function bench_map_multi_collection(): void
    {
        ($this->mapFn)($this->add, $this->ints, $this->ints);
    }

    /**
     * Realized, so the pair above is readable against the cost of the elements
     * themselves rather than in isolation.
     *
     * @Revs(1000)
     */
    public function bench_map_realized(): void
    {
        ($this->count)(($this->mapFn)($this->inc, $this->ints));
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

    /**
     * `slice` took `[coll & [offset & [length]]]`, a rest destructure nested
     * twice, and `take-last` routes through it (#3021 A3). Paired with the
     * `SliceInterface` call the body now reaches directly: 2.46μs to 0.76μs
     * against a ~0.12μs empty-closure floor.
     *
     * @Revs(1000)
     */
    public function bench_slice(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->slice)($this->vector, 1, 5);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_slice_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            $this->vector->slice(1, 5);
        }
    }

    /**
     * The transducer arity of the twelve functions split in #3021 A2. It is
     * where the split shows most, because no realization dilutes it: `take`
     * 0.66μs to 0.21μs, `distinct` 0.62μs to 0.18μs.
     *
     * Unpaired: what it measures is the cost of handing back the transducer,
     * which has no raw-PHP twin.
     *
     * @Revs(1000)
     */
    public function bench_take_transducer(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->take)(2);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_distinct_transducer(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->distinct)();
        }
    }

    /**
     * `dissoc`, `concat` and `swap!` took a rest argument for counts that are
     * almost always one or two (#3021 A4). `concat` is the sharpest: joining
     * two collections built a PHP array of them and went through
     * `call_user_func_array`, where `Seq::concat` is variadic in PHP already.
     *
     * Floor-subtracted, same process: `concat` 2.22μs to 0.73μs, `dissoc`
     * 2.27μs to 0.98μs, `swap!` 1.75μs to 0.58μs at one extra argument.
     *
     * @Revs(1000)
     */
    public function bench_concat_two(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->concat)($this->vector, $this->vector);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_dissoc_one_key(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->dissoc)($this->map, $this->dissocKey);
        }
    }

    /**
     * The constructors and predicates that still dispatched on a rest
     * argument's count after #3065 and #3067. `comp` and `repeatedly` are the
     * sharpest, and `==` is the one on a hot path: floor-subtracted, `==` of
     * two numbers went 1.56μs to 0.49μs.
     *
     * @Revs(1000)
     */
    public function bench_num_equals_two(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->numEquals)(1, 1.0);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_comp_two(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->comp)($this->inc, $this->inc);
        }
    }

    /**
     * `comp` above measures building the combinator. These four measure
     * *calling* what a combinator handed back, which is the side that runs
     * once per element when the result is used as a predicate. The closures
     * are built once, in `setUpFixtures`, so what is timed is the call.
     *
     * @Revs(1000)
     */
    public function bench_complement_call(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->notEven)($i);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_constantly_call(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->always)($i);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_some_fn_call(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->someFn)($i);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_every_pred_call(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->everyPred)($i);
        }
    }

    /**
     * `frequencies` and a map-target `into` both write a transient map once
     * per element, which no subject reached: the `into` pair above uses a
     * vector target and so goes through `conj` instead. `frequencies` now
     * calls `.find`/`.put` directly rather than `get`/`assoc!`, 97.6μs to
     * 88.1μs; `into` still goes through `assoc!` and is the control that says
     * so, having stayed at 105μs across the change.
     *
     * @Revs(1000)
     */
    public function bench_frequencies(): void
    {
        ($this->frequencies)($this->ints);
    }

    /**
     * @Revs(1000)
     */
    public function bench_into_map(): void
    {
        ($this->into)($this->emptyMap, $this->pairs);
    }

    /**
     * Two passes over a transient map, one to bucket and one to make each
     * bucket persistent. Both moved off `get`/`assoc` and `for … :pairs`:
     * 51.8μs to 34.6μs.
     *
     * @Revs(1000)
     */
    public function bench_group_by(): void
    {
        ($this->groupBy)($this->isEven, $this->ints);
    }

    /**
     * The subjects above hand back a transducer and stop there, so none of
     * them reaches the reducing function it wraps. This one drives 32 elements
     * through it, which is where a per-step cost would show up.
     *
     * @Revs(1000)
     */
    public function bench_transduce_map(): void
    {
        ($this->transduce)(($this->mapFn)($this->inc), $this->add, 0, $this->ints);
    }

    /**
     * @Revs(1000)
     */
    public function bench_transduce_filter(): void
    {
        ($this->transduce)(($this->filterFn)($this->isEven), $this->add, 0, $this->ints);
    }

    /**
     * The `distinct` transducer actually stepping. `bench_distinct_transducer`
     * above builds it and stops, so the `seen` accumulator it maintains per
     * element was never measured.
     *
     * @Revs(1000)
     */
    public function bench_transduce_distinct(): void
    {
        ($this->transduce)(($this->distinct)(), $this->add, 0, $this->ints);
    }

    /**
     * `zipmap` was the one map builder in core still accumulating a persistent
     * map, so it paid a HAMT path copy per pair where `select-keys`,
     * `update-keys`, `update-vals` and `rename-keys` all build through a
     * transient.
     *
     * @Revs(1000)
     */
    public function bench_zipmap(): void
    {
        ($this->zipmap)($this->ints, $this->ints);
    }

    /**
     * Counting a lazy sequence. `count` dispatches to `Countable::count()`,
     * which materialised every element into a PHP array only to measure it.
     * The vector and map subjects in `CoreDispatchBench` are O(1) reads and so
     * never reached this path.
     *
     * @Revs(1000)
     */
    public function bench_count_lazy_seq(): void
    {
        ($this->count)(($this->mapFn)($this->inc, $this->ints));
    }

    /**
     * `vec` and `into []` build a vector from a collection. Both appended
     * through a transient once per element; the raw pair below is the single
     * bulk construction the same result can be built with.
     *
     * @Revs(1000)
     */
    public function bench_vec_from_vector(): void
    {
        ($this->vec)($this->ints);
    }

    /**
     * @Revs(1000)
     */
    public function bench_vec_from_vector_raw(): void
    {
        Phel::vector($this->intArray);
    }

    /**
     * `kvs` and the indexed branch of `php->phel` build a vector the same way
     * `vec` did before #3134: one transient append per element. `php->phel` is
     * an interop boundary, so it sits on the path of anything decoding PHP
     * data.
     *
     * @Revs(1000)
     */
    public function bench_kvs(): void
    {
        ($this->kvs)($this->map);
    }

    /**
     * @Revs(1000)
     */
    public function bench_php_to_phel_indexed(): void
    {
        ($this->phpToPhel)($this->intArray);
    }

    /**
     * `merge` had no subject, which is why it went unnoticed that merging two
     * maps added the right one's entries through `conj`, one Phel call each,
     * rather than using the collection's own bulk merge.
     *
     * @Revs(1000)
     */
    public function bench_merge_two_maps(): void
    {
        ($this->merge)($this->bigMapA, $this->bigMapB);
    }

    /**
     * The map rebuilders. None had a subject, and all three walked their input
     * with `for … :pairs`, whose destructuring was most of what they cost.
     *
     * @Revs(1000)
     */
    public function bench_update_vals(): void
    {
        ($this->updateVals)($this->bigMapA, $this->inc);
    }

    /**
     * @Revs(1000)
     */
    public function bench_update_keys(): void
    {
        ($this->updateKeys)($this->bigMapA, $this->identity);
    }

    /**
     * @Revs(1000)
     */
    public function bench_rename_keys(): void
    {
        ($this->renameKeys)($this->bigMapA, $this->emptyMap);
    }

    /**
     * The last two map builders that walked with `for … :pairs`.
     *
     * @Revs(1000)
     */
    public function bench_merge_with(): void
    {
        ($this->mergeWith)($this->add, $this->bigMapA, $this->bigMapB);
    }

    /**
     * @Revs(1000)
     */
    public function bench_map_invert(): void
    {
        ($this->mapInvert)($this->bigMapA);
    }

    /**
     * `invert` and `map-invert` are two names for the same operation.
     * `map-invert` was moved onto `foreach` and `.put` and `invert` was
     * missed, which is exactly the drift a subject next to its twin makes
     * visible: it was 51.6μs against 19.7μs in-language before they were
     * brought back together.
     *
     * @Revs(1000)
     */
    public function bench_invert(): void
    {
        ($this->invert)($this->bigMapA);
    }

    /**
     * Two lookups on the source per key, not one: a key held with a `nil`
     * value has to stay selected, so the membership test cannot be folded
     * into the read. Both now go straight to the map's own methods.
     *
     * @Revs(1000)
     */
    public function bench_select_keys(): void
    {
        ($this->selectKeys)($this->bigMapA, $this->bigMapAKeys);
    }

    /**
     * The transient-set counterpart of the map subjects above: `.add` rather
     * than `conj!`, which dispatches on the target first.
     *
     * @Revs(1000)
     */
    public function bench_set(): void
    {
        ($this->setFn)($this->ints);
    }

    /**
     * `partition-by` and `dedupe` return a sequence built by
     * `lazy-seq-from-generator`, so constructing one realizes a chunk before
     * the caller has asked for a single element. These two subjects measure
     * that construction on its own, which is the cost #3061 is about and the
     * part `FIRST_CHUNK_SIZE` moves; the `_realized` pair below is what pays
     * for it, one extra chunk boundary on a sequence consumed in full.
     *
     * @Revs(1000)
     */
    public function bench_partition_by_unrealized(): void
    {
        ($this->partitionBy)($this->isEven, $this->ints);
    }

    /**
     * @Revs(1000)
     */
    public function bench_partition_by_realized(): void
    {
        ($this->count)(($this->partitionBy)($this->isEven, $this->ints));
    }

    /**
     * @Revs(1000)
     */
    public function bench_dedupe_unrealized(): void
    {
        ($this->dedupe)($this->ints);
    }

    /**
     * @Revs(1000)
     */
    public function bench_dedupe_realized(): void
    {
        ($this->count)(($this->dedupe)($this->ints));
    }

    protected function setUpFixtures(): void
    {
        $this->partitionBy = $this->coreFn('partition-by');
        $this->transduce = $this->coreFn('transduce');
        $this->vec = $this->coreFn('vec');
        $this->merge = $this->coreFn('merge');
        $this->mergeWith = $this->coreFn('merge-with');
        $this->mapInvert = $this->coreFn('map-invert');
        $this->updateVals = $this->coreFn('update-vals');
        $this->updateKeys = $this->coreFn('update-keys');
        $this->renameKeys = $this->coreFn('rename-keys');
        $this->kvs = $this->coreFn('kvs');
        $this->phpToPhel = $this->coreFn('php->phel');
        $this->zipmap = $this->coreFn('zipmap');
        $this->frequencies = $this->coreFn('frequencies');
        $this->groupBy = $this->coreFn('group-by');
        $this->invert = $this->coreFn('invert');
        $this->selectKeys = $this->coreFn('select-keys');
        $this->setFn = $this->coreFn('set');

        $this->notEven = ($this->coreFn('complement'))($this->coreFn('even?'));
        $this->always = ($this->coreFn('constantly'))(42);
        $this->someFn = ($this->coreFn('some-fn'))($this->coreFn('even?'), $this->coreFn('neg?'));
        $this->everyPred = ($this->coreFn('every-pred'))($this->coreFn('even?'), $this->coreFn('pos?'));
        $this->dedupe = $this->coreFn('dedupe');

        $this->numEquals = $this->coreFn('==');
        $this->comp = $this->coreFn('comp');

        $this->concat = $this->coreFn('concat');
        $this->dissoc = $this->coreFn('dissoc');
        // `$this->map` is the shared two-entry fixture set further down.
        $this->dissocKey = Phel::keyword('b');

        $this->slice = $this->coreFn('slice');
        $this->take = $this->coreFn('take');
        $this->distinct = $this->coreFn('distinct');
        $this->vector = Phel::vector(range(0, 19));

        $this->sort = $this->coreFn('sort');
        $this->mapFn = $this->coreFn('map');
        $this->filterFn = $this->coreFn('filter');
        $this->inc = $this->coreFn('inc');
        $this->isEven = $this->coreFn('even?');
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
        $this->emptyMap = Phel::map();

        $kvA = [];
        $kvB = [];
        for ($i = 0; $i < self::SIZE; ++$i) {
            $kvA[] = Phel::keyword('a' . $i);
            $kvA[] = $i;
            $kvB[] = Phel::keyword('b' . $i);
            $kvB[] = $i;
        }

        $this->bigMapA = Phel::map(...$kvA);
        // Every key of `bigMapA`, so `select-keys` takes the all-hit path
        // rather than measuring misses.
        $keys = [];
        for ($i = 0; $i < self::SIZE; ++$i) {
            $keys[] = Phel::keyword('a' . $i);
        }

        $this->bigMapAKeys = Phel::vector($keys);
        $this->bigMapB = Phel::map(...$kvB);

        $pairs = [];
        for ($i = 0; $i < self::SIZE; ++$i) {
            $pairs[] = Phel::vector([$i, $i * 2]);
        }

        $this->pairs = Phel::vector($pairs);
    }
}
