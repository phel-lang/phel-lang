<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Compiler\Fixtures;

use Phel;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;

/**
 * Lower-bound reference: rebuilds the persistent collections on every
 * call with no caching at all. Quantifies the cost the per-fn / ns-scope
 * caches actually save.
 */
final class CollectionEmitDirect
{
    /**
     * @return array{0: PersistentVectorInterface, 1: PersistentVectorInterface, 2: PersistentMapInterface}
     */
    public function __invoke(): array
    {
        return [
            Phel::vector([1, 2, 3]),
            Phel::vector(['a', 'b', 'c']),
            Phel::map('k', 1, 'v', 2),
        ];
    }
}
