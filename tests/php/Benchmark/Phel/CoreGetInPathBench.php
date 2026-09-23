<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Phel;

use Phel;
use Phel\Build\BuildFacade;
use Phel\Lang\Keyword;
use Phel\Run\RunFacade;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Revs;
use RuntimeException;

use function is_callable;
use function sprintf;

/**
 * A `get-in` whose path is a vector written at the call site, on a target
 * with no collection tag, which the emitter lowers to one `GetIn::path`
 * call rather than a call to `phel.core/get-in` (#3320). The target is a
 * 9-key map, the shape the issue measured.
 *
 * The lowering lives in the emitted code, so each subject compiles a Phel
 * `fn` around the call once, in `setUpFixtures`, and measures calling it, as
 * {@see CoreAssocPairsBench} does. It compiles in build mode, so the runtime
 * call it replaces keeps its call-site slot and arity shortcut.
 *
 * {@see CoreBenchCase} for the conventions every subject here follows.
 *
 * @BeforeMethods("setUp")
 */
final class CoreGetInPathBench extends CoreBenchCase
{
    private const int MAP_SIZE = 9;

    private const int DEPTH = 8;

    /** @var callable */
    private $getInTwo;

    /** @var callable */
    private $getInDeep;

    private mixed $map = null;

    private mixed $deep = null;

    /** @var list<Keyword> */
    private array $keys = [];

    /**
     * @Revs(1000)
     */
    public function bench_get_in_two_keys(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->getInTwo)($this->map);
        }
    }

    /**
     * The floor: the two subscripts the path comes down to.
     *
     * @Revs(1000)
     */
    public function bench_get_in_two_keys_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            $unused = $this->map[$this->keys[0]][$this->keys[1]] ?? null;
        }
    }

    /**
     * Eight levels deep with a default, a miss on the last key.
     *
     * @Revs(1000)
     */
    public function bench_get_in_eight_keys_default(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->getInDeep)($this->deep);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_get_in_eight_keys_default_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            $level = $this->deep;
            for ($d = 0; $d < self::DEPTH; ++$d) {
                $level = $level[$this->keys[$d]] ?? null;
            }

            $unused = $level ?? 'nf';
        }
    }

    protected function setUpFixtures(): void
    {
        for ($i = 0; $i < self::MAP_SIZE; ++$i) {
            $this->keys[] = Keyword::create('k' . $i);
        }

        $inner = Phel::map($this->keys[1], 42);
        $entries = [$this->keys[0], $inner];
        for ($i = 2; $i < self::MAP_SIZE; ++$i) {
            $entries[] = $this->keys[$i];
            $entries[] = $i;
        }

        $this->map = Phel::map(...$entries);

        // Every level holds the next under its own key, except the last,
        // which lacks `:k7`, so the walk reaches the bottom and misses.
        $deep = Phel::map();
        for ($d = self::DEPTH - 2; $d >= 0; --$d) {
            $deep = Phel::map($this->keys[$d], $deep);
        }

        $this->deep = $deep;

        $this->getInTwo = $this->compileFn('(fn [m] (get-in m [:k0 :k1]))');
        $this->getInDeep = $this->compileFn('(fn [m] (get-in m [:k0 :k1 :k2 :k3 :k4 :k5 :k6 :k7] :nf))');
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
