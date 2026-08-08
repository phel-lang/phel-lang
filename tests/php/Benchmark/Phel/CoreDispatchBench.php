<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Phel;

use Phel;
use Phel\Lang\Keyword;
use Phel\Run\RunFacade;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Revs;
use RuntimeException;

use function count;
use function dirname;
use function is_callable;
use function sprintf;

/**
 * Dispatch cost of the hot `phel.core` functions, measured through the
 * compiled Phel function rather than the class underneath it.
 *
 * {@see PhelBench} is startup-shaped: it compiles `phel.core` per iteration,
 * so a function that got twice as slow to call is invisible there. The other
 * benchmarks measure the layer *below* the core function, `NumericOperations`
 * or the persistent collections directly, so they are blind to the wrapper as
 * well. Between them, `(get ...)` and `(empty? ...)` were unmeasured.
 *
 * Every subject is paired with the raw operation the function ultimately
 * performs, `bench_x` against `bench_x_raw`. The reviewable number is the
 * ratio between the pair, not either duration: it survives a change of
 * machine, where an absolute millisecond figure does not. A ratio of 1.0
 * means the wrapper is free.
 *
 * Revs are looped internally, matching {@see \PhelTest\Benchmark\Lang\NumericOperationsBench},
 * so timer overhead is amortised rather than measured.
 *
 * Two things here look like waste and are not:
 *
 * - **The inner loop is repeated in every subject** rather than extracted to a
 *   helper taking a closure. Extracting it would put a closure invocation
 *   inside the measurement, in every subject, which is a large fraction of
 *   what these subjects cost. The duplication is the measurement.
 * - **The raw subjects assign to `$unused`.** Discarding the expression
 *   instead would let it be optimised away and leave the pair timing an
 *   empty loop, which reads as "the wrapper is infinitely slow".
 *
 * @BeforeMethods("setUp")
 */
final class CoreDispatchBench
{
    private const int INNER = 32;

    /** @var callable */
    private $get;

    /** @var callable */
    private $assoc;

    /** @var callable */
    private $getIn;

    /** @var callable */
    private $isEmpty;

    /** @var callable */
    private $seq;

    /** @var callable */
    private $conj;

    /** @var callable */
    private $int;

    private mixed $map = null;

    private mixed $vector = null;

    private mixed $list = null;

    private mixed $nested = null;

    private mixed $pathAC = null;

    private Keyword $keyA;

    private Keyword $keyC;

    /** Typed `mixed` so a cast on them is real work, not a foldable literal. */
    private mixed $rawInt = 42;

    private mixed $rawString = '42';

    private mixed $rawFloat = 1.9;

    public function setUp(): void
    {
        $root = dirname(__DIR__, 4);
        Phel::bootstrap($root);
        new RunFacade()->loadPhelNamespaces();

        $this->get = $this->coreFn('get');
        $this->assoc = $this->coreFn('assoc');
        $this->getIn = $this->coreFn('get-in');
        $this->isEmpty = $this->coreFn('empty?');
        $this->seq = $this->coreFn('seq');
        $this->conj = $this->coreFn('conj');
        $this->int = $this->coreFn('int');

        $this->keyA = Keyword::create('a');
        $this->keyC = Keyword::create('c');
        $this->map = Phel::map($this->keyA, 1, Keyword::create('b'), 2);
        $this->vector = Phel::vector([1, 2, 3]);
        $this->nested = Phel::map($this->keyA, Phel::map($this->keyC, 42));
        $this->pathAC = Phel::vector([$this->keyA, $this->keyC]);
        $this->list = Phel::list([1, 2, 3]);
    }

    /**
     * @Revs(1000)
     */
    public function bench_get_map(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->get)($this->map, $this->keyA);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_get_map_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            $this->map->find($this->keyA);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_get_vector(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->get)($this->vector, 1);
        }
    }

    /**
     * Its reference is `bench_get_map`: a two-step `get-in` is two `get` calls
     * plus the traversal around them, so anything much above twice that number
     * is the loop rather than the lookups.
     *
     * @Revs(1000)
     */
    public function bench_get_in_nested(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->getIn)($this->nested, $this->pathAC);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_assoc_map(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->assoc)($this->map, $this->keyC, 3);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_assoc_map_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            $this->map->put($this->keyC, 3);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_empty_vector(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->isEmpty)($this->vector);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_empty_vector_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            $unused = count($this->vector) === 0;
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_empty_string(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->isEmpty)('abc');
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_seq_vector(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->seq)($this->vector);
        }
    }

    /**
     * A floor, not a target, and the only pair here that is not like for like.
     *
     * `seq` on a vector falls through to `(seq-list (to-php-array coll))`, so
     * it materialises a *list*. No single native call does that, and the gap
     * between this subject and `bench_seq_vector` is mostly `Phel::seqList`
     * building the result, which is the value `seq` exists to return rather
     * than overhead to remove.
     *
     * `getIterator()` was worse: it builds nothing at all, and reported a
     * ratio near 15x that implied a win no reordering could deliver.
     *
     * @Revs(1000)
     */
    public function bench_seq_vector_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            $this->vector->toArray();
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_conj_vector(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->conj)($this->vector, 9);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_conj_vector_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            $this->vector->append(9);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_conj_list(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->conj)($this->list, 9);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_int_int(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->int)($this->rawInt);
        }
    }

    /**
     * Cast through a `mixed` property rather than a literal. `(int) 42` is a
     * redundant cast rector removes, which would leave the pair measuring an
     * empty loop instead of the coercion.
     *
     * @Revs(1000)
     */
    public function bench_int_int_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            $unused = (int) $this->rawInt;
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_int_string(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->int)($this->rawString);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_int_string_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            $unused = (int) $this->rawString;
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_int_float(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->int)($this->rawFloat);
        }
    }

    /**
     * The registry keys bundled namespaces with `.`, so `phel.core` and not
     * the `phel\core` that the compiled PHP file declares as its namespace.
     */
    private function coreFn(string $name): callable
    {
        $fn = Phel::getDefinition('phel.core', $name);
        if (!is_callable($fn)) {
            throw new RuntimeException(sprintf('phel.core/%s is not callable; is core loaded?', $name));
        }

        return $fn;
    }
}
