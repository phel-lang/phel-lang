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
 * An `assoc-in` or `update-in` whose path is a vector written at the call
 * site, which the emitter lowers to one `AssocIn` call rather than a call to
 * the core fn (#3328). The target is a 9-key map, the shape
 * `CoreGetInPathBench` measures.
 *
 * The lowering lives in the emitted code, so each subject compiles a Phel
 * `fn` around the call once, in `setUpFixtures`, and measures calling it, as
 * {@see CoreGetInPathBench} does. It compiles in build mode, so the runtime
 * call it replaces keeps its call-site slot and arity shortcut.
 *
 * {@see CoreBenchCase} for the conventions every subject here follows.
 *
 * @BeforeMethods("setUp")
 */
final class CoreAssocInPathBench extends CoreBenchCase
{
    private const int MAP_SIZE = 9;

    private const int DEPTH = 8;

    /** @var callable */
    private $assocInTwo;

    /** @var callable */
    private $assocInDeep;

    /** @var callable */
    private $assocInVector;

    /** @var callable */
    private $assocInMissing;

    /** @var callable */
    private $updateInTwo;

    /** @var callable */
    private $updateInArgs;

    private mixed $map = null;

    private mixed $deep = null;

    /** @var list<Keyword> */
    private array $keys = [];

    private ?Keyword $absent = null;

    /** @var callable */
    private $inc;

    /** @var callable */
    private $plus;

    /**
     * @Revs(1000)
     */
    public function bench_assoc_in_two_keys(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->assocInTwo)($this->map);
        }
    }

    /**
     * The floor: the read and the two writes the path comes down to.
     *
     * @Revs(1000)
     */
    public function bench_assoc_in_two_keys_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            $unused = $this->map->put($this->keys[0], $this->map[$this->keys[0]]->put($this->keys[1], 1));
        }
    }

    /**
     * Eight levels deep, the last key new.
     *
     * @Revs(1000)
     */
    public function bench_assoc_in_eight_keys(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->assocInDeep)($this->deep);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_assoc_in_eight_keys_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            $levels = [$this->deep];
            for ($d = 0; $d < self::DEPTH - 1; ++$d) {
                $levels[] = $levels[$d][$this->keys[$d]] ?? null;
            }

            $res = $levels[self::DEPTH - 1]->put($this->keys[self::DEPTH - 1], 1);
            for ($d = self::DEPTH - 2; $d >= 0; --$d) {
                $res = $levels[$d]->put($this->keys[$d], $res);
            }
        }
    }

    /**
     * A map, then a vector by index, then a map.
     *
     * @Revs(1000)
     */
    public function bench_assoc_in_vector_level(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->assocInVector)($this->map);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_assoc_in_vector_level_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            $vector = $this->map[$this->keys[2]];
            $unused = $this->map->put($this->keys[2], $vector->update(1, $vector[1]->put($this->keys[1], 1)));
        }
    }

    /**
     * The first key is absent, so the walk creates the intermediate map.
     *
     * @Revs(1000)
     */
    public function bench_assoc_in_missing_level(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->assocInMissing)($this->map);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_assoc_in_missing_level_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            $unused = $this->map->put($this->absent, Phel::map()->put($this->keys[1], 1));
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_update_in_two_keys(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->updateInTwo)($this->map);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_update_in_two_keys_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            $inner = $this->map[$this->keys[0]];
            $unused = $this->map->put($this->keys[0], $inner->put($this->keys[1], ($this->inc)($inner[$this->keys[1]])));
        }
    }

    /**
     * `f` with two extra arguments, which the runtime spreads through `apply`
     * at every level.
     *
     * @Revs(1000)
     */
    public function bench_update_in_extra_args(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->updateInArgs)($this->map);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_update_in_extra_args_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            $inner = $this->map[$this->keys[0]];
            $unused = $this->map->put($this->keys[0], $inner->put($this->keys[1], ($this->plus)($inner[$this->keys[1]], 1, 2)));
        }
    }

    protected function setUpFixtures(): void
    {
        for ($i = 0; $i < self::MAP_SIZE; ++$i) {
            $this->keys[] = Keyword::create('k' . $i);
        }

        $inner = Phel::map($this->keys[1], 42);
        $entries = [
            $this->keys[0], $inner,
            $this->keys[2], Phel::vector([$inner, $inner]),
        ];
        for ($i = 3; $i < self::MAP_SIZE; ++$i) {
            $entries[] = $this->keys[$i];
            $entries[] = $i;
        }

        $this->map = Phel::map(...$entries);

        // Every level holds the next under its own key, except the last,
        // which lacks `:k7`, so the write adds it at the bottom.
        $deep = Phel::map();
        for ($d = self::DEPTH - 2; $d >= 0; --$d) {
            $deep = Phel::map($this->keys[$d], $deep);
        }

        $this->deep = $deep;
        $this->absent = Keyword::create('absent');
        $this->inc = $this->coreFn('inc');
        $this->plus = $this->coreFn('+');

        $this->assocInTwo = $this->compileFn('(fn [m] (assoc-in m [:k0 :k1] 1))');
        $this->assocInDeep = $this->compileFn('(fn [m] (assoc-in m [:k0 :k1 :k2 :k3 :k4 :k5 :k6 :k7] 1))');
        $this->assocInVector = $this->compileFn('(fn [m] (assoc-in m [:k2 1 :k1] 1))');
        $this->assocInMissing = $this->compileFn('(fn [m] (assoc-in m [:absent :k1] 1))');
        $this->updateInTwo = $this->compileFn('(fn [m] (update-in m [:k0 :k1] inc))');
        $this->updateInArgs = $this->compileFn('(fn [m] (update-in m [:k0 :k1] + 1 2))');
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
