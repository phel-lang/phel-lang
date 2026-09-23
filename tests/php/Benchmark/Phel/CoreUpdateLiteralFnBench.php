<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Phel;

use Phel;
use Phel\Build\BuildFacade;
use Phel\Lang\Keyword;
use Phel\Run\RunFacade;
use Phel\Shared\CompileOptions;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Revs;
use RuntimeException;

use function is_callable;
use function max;
use function sprintf;

/**
 * An `update` whose function is a literal `fn` written at the call site,
 * which the emitter lowers to a read, the spliced body and one `assoc`
 * instead of building a closure and calling `phel.core/update` (#3322).
 * The target is a 90-key hash map, the shape the issue measured.
 *
 * The lowering lives in the emitted code, so each subject compiles a Phel
 * `fn` around the call once, in `setUpFixtures`, and measures calling it, as
 * {@see CoreAssocPairsBench} does. It compiles in build mode, so the runtime
 * call it replaces keeps its call-site slot and arity shortcut, and at
 * optimization level 2, the only level that splices the body: the lowering
 * is direct linking, which `with-redefs` could not intercept.
 *
 * {@see CoreBenchCase} for the conventions every subject here follows.
 *
 * @BeforeMethods("setUp")
 */
final class CoreUpdateLiteralFnBench extends CoreBenchCase
{
    private const int MAP_SIZE = 90;

    /** @var callable */
    private $updateInc;

    /** @var callable */
    private $updateDecay;

    /** @var callable */
    private $updateThreaded;

    private mixed $map = null;

    private ?Keyword $key = null;

    private ?Keyword $otherKey = null;

    /**
     * @Revs(1000)
     */
    public function bench_update_literal_fn(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->updateInc)($this->map);
        }
    }

    /**
     * The floor: the read and the write the update comes down to.
     *
     * @Revs(1000)
     */
    public function bench_update_literal_fn_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            (void) $this->map->put($this->key, $this->map->find($this->key) + 1);
        }
    }

    /**
     * The per-frame timer decay the issue quotes: an extra local captured by
     * the body and an `or` around the current value.
     *
     * @Revs(1000)
     */
    public function bench_update_literal_fn_decay(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->updateDecay)($this->map, 0.5);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_update_literal_fn_decay_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            (void) $this->map->put($this->key, max(0.0, ($this->map->find($this->key) ?? 0.0) - 0.5));
        }
    }

    /**
     * Two updates threaded with `->`: the inner one sits in expression
     * position, where its bindings are chained inside the outer expression.
     *
     * @Revs(1000)
     */
    public function bench_update_literal_fn_threaded(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->updateThreaded)($this->map);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_update_literal_fn_threaded_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            $inner = $this->map->put($this->key, $this->map->find($this->key) + 1);
            (void) $inner->put($this->otherKey, $inner->find($this->otherKey) - 1);
        }
    }

    protected function setUpFixtures(): void
    {
        $entries = [];
        for ($i = 0; $i < self::MAP_SIZE; ++$i) {
            $entries[] = Keyword::create('k' . $i);
            $entries[] = $i;
        }

        $this->map = Phel::map(...$entries);
        $this->key = Keyword::create('k5');
        $this->otherKey = Keyword::create('k6');

        $this->updateInc = $this->compileFn('(fn [m] (update m :k5 (fn [v] (php/+ v 1))))');
        $this->updateDecay = $this->compileFn('(fn [m dt] (update m :k5 (fn [s] (php/max 0.0 (php/- (or s 0.0) dt)))))');
        $this->updateThreaded = $this->compileFn('(fn [m] (-> m (update :k5 (fn [v] (php/+ v 1))) (update :k6 (fn [v] (php/- v 1)))))');
    }

    private function compileFn(string $phelCode): callable
    {
        BuildFacade::enableBuildMode();
        try {
            $fn = new RunFacade()->eval($phelCode, new CompileOptions()->setOptimizationLevel(2));
        } finally {
            BuildFacade::disableBuildMode();
        }

        if (!is_callable($fn)) {
            throw new RuntimeException(sprintf('%s did not evaluate to a callable', $phelCode));
        }

        return $fn;
    }
}
