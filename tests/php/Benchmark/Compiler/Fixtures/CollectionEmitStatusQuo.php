<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Compiler\Fixtures;

use Phel;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;

/**
 * Status-quo emission for collection literals inside a fn body:
 * per-fn `static $__phel_const_N` slot reserved by `BodyConstantScanner`.
 * After the first call each `??=` short-circuits and returns the cached
 * persistent collection. Steady-state cost is one static read per slot.
 */
final class CollectionEmitStatusQuo
{
    /**
     * @return array{0: PersistentVectorInterface, 1: PersistentVectorInterface, 2: PersistentMapInterface}
     */
    public function __invoke(): array
    {
        static $c0;
        static $c1;
        static $c2;
        $c0 ??= Phel::vector([1, 2, 3]);
        $c1 ??= Phel::vector(['a', 'b', 'c']);
        $c2 ??= Phel::map('k', 1, 'v', 2);

        return [$c0, $c1, $c2];
    }
}
