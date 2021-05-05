<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Lang\Collections\Map;

use Phel\Lang\Collections\Map\PersistentHashMap;
use PhelTest\Benchmark\Lang\Collections\SimpleEqualizer;
use PhelTest\Benchmark\Lang\Collections\SimpleHasher;

/**
 * @BeforeMethods("setUp")
 */
final class PersistentHashMapBench
{
    private PersistentHashMap $map;

    public function setUp(): void
    {
        $this->map = PersistentHashMap::empty(new SimpleHasher(), new SimpleEqualizer());
    }

    /**
     * @Iterations(5)
     * @Revs(1000)
     */
    public function bench_hash_map_put(): void
    {
        $i = random_int(1, PHP_INT_MAX);
        $this->map->put($i, $i);
    }
}
