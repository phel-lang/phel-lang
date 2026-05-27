<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Compiler\Fixtures;

use Phel;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;

/**
 * Hypothetical per-ns shared cache for collection literals (issue
 * #2138). Replaces the per-fn `static $__phel_const_N` slot with a
 * static array lookup on a parent class, so multiple fns of the same
 * ns share a single instance per literal.
 */
final class CollectionEmitNsScope
{
    /** @var array<string, PersistentMapInterface|PersistentVectorInterface> */
    public static array $cache = [];

    /**
     * @return array{0: PersistentVectorInterface, 1: PersistentVectorInterface, 2: PersistentMapInterface}
     */
    public function __invoke(): array
    {
        return [
            self::$cache['v0'] ??= Phel::vector([1, 2, 3]),
            self::$cache['v1'] ??= Phel::vector(['a', 'b', 'c']),
            self::$cache['m0'] ??= Phel::map('k', 1, 'v', 2),
        ];
    }
}
