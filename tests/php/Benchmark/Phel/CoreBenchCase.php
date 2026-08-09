<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Phel;

use Phel;
use Phel\Run\RunFacade;
use RuntimeException;

use function dirname;
use function is_callable;
use function sprintf;

/**
 * Shared bootstrap for the benchmarks that measure a `phel.core` function
 * through the compiled Phel function rather than the class underneath it.
 *
 * {@see CoreDispatchBench} documents why these benchmarks are shaped the way
 * they are; its rules apply to every subclass here:
 *
 * - subjects come in pairs, `bench_x` against `bench_x_raw`, and the reviewable
 *   number is the ratio between them rather than either duration, because a
 *   ratio survives a change of machine where an absolute figure does not;
 * - the inner loop is repeated in each subject rather than extracted behind a
 *   closure, because a closure invocation inside the measurement is a large
 *   fraction of what an O(1) subject costs;
 * - a raw subject whose body is a bare operator expression (`$a + 1`) assigns
 *   to `$unused`, so the statement cannot be optimised away and leave the pair
 *   timing an empty loop. Where the body is a call, the call itself is the
 *   work and the assignment is not needed; Rector strips it.
 *
 * Only the bootstrap is shared. Nothing that runs inside a measured region
 * lives here.
 */
abstract class CoreBenchCase
{
    /**
     * Iterations folded into one subject for O(1) functions, so timer overhead
     * is amortised rather than measured. Subjects whose cost already grows with
     * the input (`sort`, `reduce`, `interleave`) call once per rev instead: the
     * timer is negligible against them, and looping would only make the run
     * slower without making it more stable.
     */
    protected const int INNER = 32;

    /**
     * `phel.core` only, not `loadPhelNamespaces()`, which pulls in every
     * bundled namespace (`phel.test`, `phel.html`, `phel.http`, ...) that no
     * subject here touches.
     *
     * phpbench runs each iteration in a fresh process, so this cost is paid
     * once per iteration per subject rather than once per run: 0.22s against
     * 0.05s, multiplied by several hundred. It is the largest single term in
     * how long the benchmark suite takes, and none of it is measured time.
     */
    public function setUp(): void
    {
        Phel::bootstrap(dirname(__DIR__, 4));
        new RunFacade()->runNamespace('phel.core');

        $this->setUpFixtures();
    }

    /**
     * Resolve the functions and build the data the subjects need. Runs after
     * `phel.core` is loaded, so `coreFn()` is usable from here.
     */
    abstract protected function setUpFixtures(): void;

    /**
     * The registry keys bundled namespaces with `.`, so `phel.core` and not
     * the `phel\core` that the compiled PHP file declares as its namespace.
     */
    final protected function coreFn(string $name): callable
    {
        $fn = Phel::getDefinition('phel.core', $name);
        if (!is_callable($fn)) {
            throw new RuntimeException(sprintf('phel.core/%s is not callable; is core loaded?', $name));
        }

        return $fn;
    }
}
