<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Lang\Collections\HashMap;

use Phel\Lang\Collections\HashMap\PersistentHashMap;

final class PersistentHashMapAddBench
{
    private const MAX_ADD = 1000;

    /**
     * @Revs(1000)
     */
    public function benchHashMapPut(): void
    {
        $map = PersistentHashMap::empty(new SimpleHasher(), new SimpleEqualizer());
        for ($i = 0; $i < self::MAX_ADD; $i++) {
            $map = $map->put($i, $i);
        }
    }

    /**
     * @Revs(1000)
     */
    public function benchPhpArrayCopyAndPut(): void
    {
        $map = [];
        for ($i = 0; $i < self::MAX_ADD; $i++) {
            $newMap = $map;
            $newMap[$i] = $i;
            $map = $newMap;
        }
    }
}
