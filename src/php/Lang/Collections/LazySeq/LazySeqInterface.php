<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\LazySeq;

use Phel\Lang\ConsInterface;
use Phel\Lang\SeqInterface;
use Phel\Lang\TypeInterface;

/**
 * Interface for lazy sequences.
 * Lazy sequences defer computation until values are actually needed.
 *
 * @template T
 *
 * @extends SeqInterface<T, LazySeqInterface<T>>
 * @extends ConsInterface<LazySeqInterface<T>>
 */
interface LazySeqInterface extends TypeInterface, SeqInterface, ConsInterface
{
    /**
     * Checks if this lazy sequence has been realized (computed).
     */
    public function isRealized(): bool;

    /**
     * Realizes one step beyond the head and returns a `LazyCons` cell whose
     * `cdr` is the lazy tail, or `null` when the tail is empty. Mirrors
     * Clojure's `(next s)`; the returned value is never a `LazySeqInterface`.
     *
     * @return LazyCons<mixed>|null
     */
    public function nextSeq(): ?LazyCons;

    /**
     * Forces realization of the entire sequence and returns it as an array.
     *
     * Warning: This will cause infinite sequences to run forever.
     *
     * @return array<int, T>
     */
    public function toArray(): array;
}
