<?php

declare(strict_types=1);

namespace Phel\Lang;

use Iterator;
use IteratorAggregate;
use Traversable;

/**
 * Unwraps a `Traversable` into an `\Iterator` that can be driven directly
 * with `rewind`/`valid`/`current`/`next`.
 *
 * Generators already are iterators, but an `IteratorAggregate` returns a
 * nested `Traversable` (possibly several levels deep) that must be unwrapped
 * first. Collapses the four byte-identical private unwrap helpers that lived
 * on `AbstractPersistentVector`, `Cons`, `LazySeq` and `ChunkedSeq`.
 */
final readonly class IteratorUnwrapper
{
    private function __construct() {}

    /**
     * @param Traversable<mixed, mixed> $traversable
     *
     * @return Iterator<mixed, mixed>
     */
    public static function unwrap(Traversable $traversable): Iterator
    {
        while ($traversable instanceof IteratorAggregate) {
            $traversable = $traversable->getIterator();
        }

        /** @var Iterator<mixed, mixed> $traversable */
        return $traversable;
    }
}
