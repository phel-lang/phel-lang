<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Lang\Collections\Map;

use Phel\Lang\Collections\Map\PersistentHashMap;
use PhelTest\Benchmark\Lang\Collections\SimpleEqualizer;
use PhelTest\Benchmark\Lang\Collections\SimpleHasher;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;

/**
 * @BeforeMethods("setUp")
 */
final class PersistentHashMapBench
{
    private const int SEED_SIZE = 128;

    private PersistentHashMap $map;

    private int $nextKey = 0;

    public function setUp(): void
    {
        $this->map = PersistentHashMap::empty(new SimpleHasher(), new SimpleEqualizer());

        for ($i = 0; $i < self::SEED_SIZE; ++$i) {
            $this->map = $this->map->put($i, $i);
        }

        $this->nextKey = self::SEED_SIZE;
    }

    public function bench_hash_map_put(): void
    {
        $this->map->put($this->nextKey, $this->nextKey);
        ++$this->nextKey;
    }
}
