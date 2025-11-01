<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Lang\Collections\Map;

use Phel\Lang\Collections\Map\PersistentHashMap;
use PhelTest\Benchmark\Lang\Collections\SimpleEqualizer;
use PhelTest\Benchmark\Lang\Collections\SimpleHasher;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\ParamProviders;

/**
 * @BeforeMethods("setUp")
 */
final class PersistentHashMapBench
{
    private PersistentHashMap $map;

    private int $nextKey = 0;

    private int $queryKey = 0;

    /**
     * @return array<string, array<string, int>>
     */
    public function provideSizes(): array
    {
        return [
            'small' => ['size' => 16],
            'medium' => ['size' => 128],
            'large' => ['size' => 256],
        ];
    }

    public function setUpMap(int $size): void
    {
        $this->map = PersistentHashMap::empty(new SimpleHasher(), new SimpleEqualizer());

        for ($i = 0; $i < $size; ++$i) {
            $this->map = $this->map->put($i, $i);
        }

        $this->nextKey = $size;
        $this->queryKey = intdiv($size, 2);
    }

    /**
     * @ParamProviders("provideSizes")
     */
    public function bench_put(array $params): void
    {
        $this->setUpMap($params['size']);
        $this->map->put($this->nextKey, $this->nextKey);
    }

    /**
     * @ParamProviders("provideSizes")
     */
    public function bench_find(array $params): void
    {
        $this->setUpMap($params['size']);
        $this->map->find($this->queryKey);
    }

    /**
     * @ParamProviders("provideSizes")
     */
    public function bench_contains(array $params): void
    {
        $this->setUpMap($params['size']);
        $this->map->contains($this->queryKey);
    }

    /**
     * @ParamProviders("provideSizes")
     */
    public function bench_remove(array $params): void
    {
        $this->setUpMap($params['size']);
        $this->map->remove($this->queryKey);
    }

    /**
     * @ParamProviders("provideSizes")
     */
    public function bench_count(array $params): void
    {
        $this->setUpMap($params['size']);
        $this->map->count();
    }
}
