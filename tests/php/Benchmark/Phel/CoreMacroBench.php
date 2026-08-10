<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Phel;

use Phel;
use Phel\Run\RunFacade;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Revs;
use RuntimeException;

use function is_callable;
use function sprintf;

/**
 * `for`, whose collect path (#2998) and `:reduce` path (#3000) were both
 * rewritten with no benchmark guarding either.
 *
 * `for` is a macro, so there is nothing to resolve out of the registry: the
 * optimisation lives in the code it expands to. Each subject therefore compiles
 * a Phel `fn` around the comprehension once, in `setUpFixtures`, and measures
 * calling it. Compilation happens before the measured region, so what is timed
 * is the expansion running rather than the compiler producing it.
 *
 * That makes this class the pattern for benchmarking any macro, and the reason
 * `for` could not simply be added to {@see CoreSeqBench}.
 *
 * {@see CoreBenchCase} for the conventions every subject here follows.
 *
 * @BeforeMethods("setUp")
 */
final class CoreMacroBench extends CoreBenchCase
{
    private const int SIZE = 32;

    /** @var callable */
    private $collect;

    /** @var callable */
    private $reduceSum;

    /** @var callable */
    private $collectFiltered;

    /** @var callable */
    private $range;

    /** @var list<int> */
    private array $intArray = [];

    private mixed $ints = null;

    /**
     * The collect path: the comprehension accumulates into a PHP array and
     * converts once at the end.
     *
     * @Revs(1000)
     */
    public function bench_for_collect(): void
    {
        ($this->collect)($this->ints);
    }

    /**
     * The floor. It skips the `inc` call per element, so the gap is the body
     * rather than the comprehension around it.
     *
     * @Revs(1000)
     */
    public function bench_for_collect_raw(): void
    {
        $result = [];
        foreach ($this->intArray as $value) {
            $result[] = $value + 1;
        }

        Phel::vector($result);
    }

    /**
     * The `:reduce` path, which threads the accumulator through a PHP cell
     * rather than rebinding it.
     *
     * @Revs(1000)
     */
    public function bench_for_reduce(): void
    {
        ($this->reduceSum)($this->ints);
    }

    /**
     * @Revs(1000)
     */
    public function bench_for_reduce_raw(): void
    {
        $acc = 0;
        foreach ($this->intArray as $value) {
            $acc += $value;
        }
    }

    /**
     * A `:when` modifier, which puts a branch inside the loop body and halves
     * how often the accumulator is touched.
     *
     * @Revs(1000)
     */
    public function bench_for_collect_when(): void
    {
        ($this->collectFiltered)($this->ints);
    }

    /**
     * `:range`, which drives the loop from `range` instead of an existing
     * collection and so never touches a persistent vector on the way in.
     *
     * @Revs(1000)
     */
    public function bench_for_range(): void
    {
        ($this->range)(self::SIZE);
    }

    protected function setUpFixtures(): void
    {
        for ($i = 0; $i < self::SIZE; ++$i) {
            $this->intArray[] = $i;
        }

        $this->ints = Phel::vector($this->intArray);

        $this->collect = $this->compileFn('(fn [coll] (for [x :in coll] (inc x)))');
        $this->reduceSum = $this->compileFn('(fn [coll] (for [x :in coll :reduce [acc 0]] (+ acc x)))');
        $this->collectFiltered = $this->compileFn('(fn [coll] (for [x :in coll :when (even? x)] x))');
        $this->range = $this->compileFn('(fn [n] (for [x :range [0 n]] x))');
    }

    private function compileFn(string $phelCode): callable
    {
        $fn = new RunFacade()->eval($phelCode);
        if (!is_callable($fn)) {
            throw new RuntimeException(sprintf('%s did not evaluate to a callable', $phelCode));
        }

        return $fn;
    }
}
