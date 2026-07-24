<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\LazySeq;

use IteratorAggregate;
use Phel\Lang\ConsInterface;
use Phel\Lang\SeqInterface;
use Phel\Lang\TypeInterface;

/**
 * Interface for lazy sequences.
 * Lazy sequences defer computation until values are actually needed.
 *
 * Iterability is part of the contract: consumers such as the printer walk a
 * lazy seq element by element instead of forcing `toArray()`, which would
 * never terminate for an infinite sequence.
 *
 * @template T
 *
 * @extends SeqInterface<T, LazySeqInterface<T>>
 * @extends ConsInterface<LazySeqInterface<T>>
 * @extends IteratorAggregate<int, T>
 */
interface LazySeqInterface extends TypeInterface, SeqInterface, ConsInterface, IteratorAggregate
{
    /**
     * Checks if this lazy sequence has been realized (computed).
     */
    public function isRealized(): bool;

    /**
     * Realizes one step beyond the head and returns a `Cons` cell whose
     * `cdr` is the lazy tail, or `null` when the tail is empty. Mirrors
     * Clojure's `(next s)`; the returned value is never a `LazySeqInterface`.
     *
     * @return Cons<mixed>|null
     */
    public function nextSeq(): ?Cons;

    /**
     * Forces realization of the entire sequence and returns it as an array.
     *
     * Warning: This will cause infinite sequences to run forever.
     *
     * @return array<int, T>
     */
    public function toArray(): array;
}
