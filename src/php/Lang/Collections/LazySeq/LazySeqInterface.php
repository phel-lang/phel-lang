<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\LazySeq;

use Phel\Lang\SeqInterface;

/**
 * Interface for lazy sequences.
 * Lazy sequences defer computation until values are actually needed.
 *
 * @template T
 *
 * @extends SeqInterface<T, LazySeqInterface<T>>
 */
interface LazySeqInterface extends SeqInterface
{
    /**
     * Checks if this lazy sequence has been realized (computed).
     */
    public function isRealized(): bool;

    /**
     * Forces realization of the entire sequence and returns it as an array.
     *
     * Warning: This will cause infinite sequences to run forever.
     *
     * @return array<int, T>
     */
    public function toArray(): array;
}
