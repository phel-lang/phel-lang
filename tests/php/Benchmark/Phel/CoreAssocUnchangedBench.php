<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Phel;

use Phel;
use Phel\Lang\Keyword;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Revs;

/**
 * `assoc` of the value a key or index already holds, which hands the
 * collection back instead of copying it (#3321), next to `assoc` of a new
 * value, which still copies. The changed subjects are here to show the
 * identity check did not make the ordinary write slower.
 *
 * The map is 90 keys, a trie two levels deep, the shape the issue measured;
 * the vector is 100 elements and the index sits in its root, not its tail.
 *
 * {@see CoreBenchCase} for the conventions every subject here follows.
 *
 * @BeforeMethods("setUp")
 */
final class CoreAssocUnchangedBench extends CoreBenchCase
{
    private const int MAP_SIZE = 90;

    private const int VECTOR_SIZE = 100;

    private const int INDEX = 5;

    /** @var callable */
    private $assoc;

    /** @var callable */
    private $update;

    /** @var callable */
    private $identity;

    private mixed $map = null;

    private mixed $vector = null;

    private ?Keyword $key = null;

    /**
     * @Revs(1000)
     */
    public function bench_assoc_unchanged_map(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->assoc)($this->map, $this->key, 7);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_assoc_unchanged_map_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            (void) $this->map->put($this->key, 7);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_assoc_changed_map(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->assoc)($this->map, $this->key, 8);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_assoc_changed_map_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            (void) $this->map->put($this->key, 8);
        }
    }

    /**
     * `(update m k identity)`: the read, the call and a write of what was read.
     *
     * @Revs(1000)
     */
    public function bench_update_unchanged_map(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->update)($this->map, $this->key, $this->identity);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_update_unchanged_map_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            (void) $this->map->put($this->key, $this->map->find($this->key));
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_assoc_unchanged_vector(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->assoc)($this->vector, self::INDEX, self::INDEX);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_assoc_unchanged_vector_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            (void) $this->vector->update(self::INDEX, self::INDEX);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_assoc_changed_vector(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->assoc)($this->vector, self::INDEX, -1);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_assoc_changed_vector_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            (void) $this->vector->update(self::INDEX, -1);
        }
    }

    protected function setUpFixtures(): void
    {
        $this->assoc = $this->coreFn('assoc');
        $this->update = $this->coreFn('update');
        $this->identity = $this->coreFn('identity');

        $entries = [];
        for ($i = 0; $i < self::MAP_SIZE; ++$i) {
            $entries[] = Keyword::create('k' . $i);
            $entries[] = $i;
        }

        $this->map = Phel::map(...$entries);
        $this->key = Keyword::create('k7');
        $this->vector = Phel::vector(range(0, self::VECTOR_SIZE - 1));
    }
}
