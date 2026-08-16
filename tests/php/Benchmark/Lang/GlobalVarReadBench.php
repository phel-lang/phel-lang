<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Lang;

use Phel;
use Phel\Lang\DynamicScope;
use Phel\Lang\Registry;
use PhpBench\Benchmark\Metadata\Annotations\AfterMethods;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\Revs;

/**
 * What a compiled global read costs.
 *
 * Every resolved symbol in generated PHP becomes a `\Phel::getDefinition()`
 * call, so this is one of the most-executed lines in any Phel program, and
 * #3179 measured it at 10.5% of runtime in a profile. Nothing in the suite
 * timed it: the `phel.core` subjects measure functions *through* a read, and
 * `PhelBench` is startup-shaped.
 *
 * Three shapes, because they are the ones the emitter chooses between:
 *
 * - `\Phel::getDefinition()` with nothing bound, the common case;
 * - the same with one `binding` frame open on an unrelated var, which is the
 *   steady state of `phel test` (`deftest` binds `*current-test-name*` around
 *   every test body), and which pays the per-read key concatenation of the
 *   `$boundNames` gate;
 * - the registry read underneath, which is the floor the facade sits on.
 *
 * The reviewable number is the ratio between them: the gap between the first
 * two is what a binding frame costs every unrelated read, and the gap to the
 * third is what the dynamic-scope path costs a var that can never be bound.
 *
 * Revs are looped internally so timer overhead is amortised rather than
 * measured, matching {@see NumericOperationsBench}.
 *
 * @BeforeMethods("setUp")
 */
final class GlobalVarReadBench
{
    private const int INNER = 32;

    private const string NS = 'bench.globals';

    private const string NAME = 'answer';

    private const string BOUND_NS = 'bench.globals';

    private const string BOUND_NAME = 'unrelated';

    public function setUp(): void
    {
        Registry::getInstance()->addDefinition(self::NS, self::NAME, 42);
        Registry::getInstance()->addDefinition(self::BOUND_NS, self::BOUND_NAME, 0);
    }

    /**
     * @Revs(1000)
     *
     * @Iterations(10)
     */
    public function bench_facade_read_idle(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            Phel::getDefinition(self::NS, self::NAME);
        }
    }

    /**
     * The same read while an unrelated var is bound. `$boundNames` keeps this
     * off the scope lookup, but the key it tests still has to be built.
     *
     * @BeforeMethods({"setUp", "pushBinding"})
     *
     * @AfterMethods("popBinding")
     *
     * @Revs(1000)
     *
     * @Iterations(10)
     */
    public function bench_facade_read_with_unrelated_binding(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            Phel::getDefinition(self::NS, self::NAME);
        }
    }

    /**
     * The floor: the registry read the facade ends at, with no gate in front.
     *
     * @Revs(1000)
     *
     * @Iterations(10)
     */
    public function bench_registry_read(): void
    {
        $registry = Registry::getInstance();
        for ($i = 0; $i < self::INNER; ++$i) {
            $registry->getDefinition(self::NS, self::NAME);
        }
    }

    public function pushBinding(): void
    {
        DynamicScope::getInstance()->pushFrame([
            self::BOUND_NS . '/' . self::BOUND_NAME => 1,
        ]);
    }

    public function popBinding(): void
    {
        DynamicScope::getInstance()->popFrame();
    }
}
