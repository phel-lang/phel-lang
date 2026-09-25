<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Phel;

use Phel;
use Phel\Build\BuildFacade;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Run\RunFacade;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Revs;
use RuntimeException;

use function is_callable;
use function sprintf;

/**
 * Sequential destructuring, `(let [[a b & r] v] ...)`, which reads a vector
 * by index and walks any other value with `first`/`next` (#3356).
 *
 * The expansion lives in the emitted code, so each subject compiles a Phel
 * `fn` around the pattern once, in `setUpFixtures`, and measures calling it,
 * as {@see CoreGetInPathBench} does. It compiles in build mode, the way a
 * deployed program runs. The value arrives as an argument, so nothing about
 * it is known at compile time.
 *
 * The list subject is the walk the fast path must not slow down.
 *
 * {@see CoreBenchCase} for the conventions every subject here follows.
 *
 * @BeforeMethods("setUp")
 */
final class CoreDestructureBench extends CoreBenchCase
{
    private const int LOOP_SIZE = 16;

    /** @var callable */
    private $twoPositions;

    /** @var callable */
    private $twoPositionsRest;

    /** @var callable */
    private $loopRest;

    /** @var PersistentVectorInterface<int> */
    private PersistentVectorInterface $vector;

    /** @var PersistentListInterface<int> */
    private PersistentListInterface $list;

    /** @var PersistentVectorInterface<int> */
    private PersistentVectorInterface $loopVector;

    /**
     * `[a b]` on a three-element vector.
     *
     * @Revs(1000)
     */
    public function bench_destructure_vector_two(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->twoPositions)($this->vector);
        }
    }

    /**
     * The floor: the two indexed reads the pattern comes down to.
     *
     * @Revs(1000)
     */
    public function bench_destructure_vector_two_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            $unused = [$this->vector[0] ?? null, $this->vector[1] ?? null];
        }
    }

    /**
     * `[a b & r]` on a three-element vector: the tail is a subvector.
     *
     * @Revs(1000)
     */
    public function bench_destructure_vector_rest(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->twoPositionsRest)($this->vector);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_destructure_vector_rest_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            $unused = [$this->vector[0] ?? null, $this->vector[1] ?? null, $this->vector->cdr()?->cdr()];
        }
    }

    /**
     * `[a b]` on a three-element list, which keeps the walk.
     *
     * @Revs(1000)
     */
    public function bench_destructure_list_two(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->twoPositions)($this->list);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_destructure_list_two_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            $unused = [$this->list->first(), $this->list->cdr()?->first()];
        }
    }

    /**
     * `(loop [[x & xs] v acc 0] ...)` summing a 16-element vector: one
     * pattern with a tail per step.
     *
     * @Revs(1000)
     */
    public function bench_destructure_loop_rest(): void
    {
        ($this->loopRest)($this->loopVector);
    }

    /**
     * @Revs(1000)
     */
    public function bench_destructure_loop_rest_raw(): void
    {
        $acc = 0;
        $xs = $this->loopVector;
        while ($xs !== null) {
            $acc += $xs[0] ?? 0;
            $xs = $xs->cdr();
        }
    }

    protected function setUpFixtures(): void
    {
        $this->vector = Phel::vector([1, 2, 3]);
        $this->list = Phel::list([1, 2, 3]);
        $this->loopVector = Phel::vector(range(1, self::LOOP_SIZE));

        $this->twoPositions = $this->compileFn('(fn [v] (let [[a b] v] b))');
        $this->twoPositionsRest = $this->compileFn('(fn [v] (let [[a b & r] v] r))');
        $this->loopRest = $this->compileFn(
            '(fn [v] (loop [[x & xs] v acc 0] (if x (recur xs (php/+ acc x)) acc)))',
        );
    }

    private function compileFn(string $phelCode): callable
    {
        BuildFacade::enableBuildMode();
        try {
            $fn = new RunFacade()->eval($phelCode);
        } finally {
            BuildFacade::disableBuildMode();
        }

        if (!is_callable($fn)) {
            throw new RuntimeException(sprintf('%s did not evaluate to a callable', $phelCode));
        }

        return $fn;
    }
}
