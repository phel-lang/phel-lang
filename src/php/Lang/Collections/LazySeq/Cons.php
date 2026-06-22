<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\LazySeq;

use IteratorAggregate;
use Phel\Lang\AbstractType;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\EqualizerInterface;
use Phel\Lang\HasherInterface;
use Phel\Lang\SeqInterface;
use Traversable;

/**
 * A realized cons cell: a concrete head paired with a lazy tail, matching
 * Clojure's `clojure.lang.Cons` and `(next some-lazy-seq)` contract.
 * Returned by `LazySeq::nextSeq()` and `ChunkedSeq::nextSeq()` so callers
 * receive a seq view that is not itself a `LazySeqInterface`.
 *
 * @template T
 *
 * @implements SeqInterface<T, LazySeqInterface>
 * @implements IteratorAggregate<int, T>
 *
 * @extends AbstractType<SeqInterface<T, LazySeqInterface>>
 */
final class Cons extends AbstractType implements SeqInterface, IteratorAggregate
{
    private ?int $hashCache = null;

    /**
     * @param T                                         $first
     * @param LazySeqInterface<T>                       $rest
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     */
    public function __construct(
        private readonly HasherInterface $hasher,
        private readonly EqualizerInterface $equalizer,
        private readonly mixed $first,
        private readonly LazySeqInterface $rest,
        private ?PersistentMapInterface $meta = null,
    ) {}

    /**
     * @return T
     */
    public function first(): mixed
    {
        return $this->first;
    }

    /**
     * @return LazySeqInterface<T>
     */
    public function cdr(): LazySeqInterface
    {
        return $this->rest;
    }

    /**
     * @return LazySeqInterface<T>
     */
    public function rest(): LazySeqInterface
    {
        return $this->rest;
    }

    /**
     * Builds a realized cons cell from a lazy `$cdr` by forcing one element
     * of the head. Returns `null` when `$cdr` is null or empty. Shared
     * helper for `LazySeq::nextSeq()`, `ChunkedSeq::nextSeq()`, and
     * `Cons::nextSeq()`.
     *
     * @param LazySeqInterface<mixed>|null $cdr
     *
     * @return self<mixed>|null
     */
    public static function fromCdr(
        HasherInterface $hasher,
        EqualizerInterface $equalizer,
        ?LazySeqInterface $cdr,
    ): ?self {
        if (!$cdr instanceof LazySeqInterface) {
            return null;
        }

        $head = $cdr->first();
        if ($head === null) {
            return null;
        }

        /** @var LazySeqInterface<mixed> $tail */
        $tail = $cdr->cdr() ?? LazySeq::empty($hasher, $equalizer);

        return new self($hasher, $equalizer, $head, $tail);
    }

    /**
     * Returns the next realized cons cell (head + lazy tail) or `null` when
     * the lazy tail is exhausted. The returned cell's head is the tail's
     * first element so callers do not skip an item.
     *
     * @return Cons<T>|null
     */
    public function nextSeq(): ?self
    {
        /** @var self<T>|null $next */
        $next = self::fromCdr($this->hasher, $this->equalizer, $this->rest);

        return $next;
    }

    /**
     * Realizes the entire sequence eagerly.
     *
     * @return array<int, T>
     */
    public function toArray(): array
    {
        $result = [$this->first];
        foreach ($this->walkTail() as $value) {
            $result[] = $value;
        }

        return $result;
    }

    /**
     * @return Traversable<int, T>
     */
    public function getIterator(): Traversable
    {
        yield $this->first;
        foreach ($this->walkTail() as $value) {
            yield $value;
        }
    }

    /**
     * @return PersistentMapInterface<mixed, mixed>|null
     */
    public function getMeta(): ?PersistentMapInterface
    {
        return $this->meta;
    }

    /**
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     */
    public function withMeta(?PersistentMapInterface $meta): static
    {
        $clone = clone $this;
        $clone->meta = $meta;

        return $clone;
    }

    public function equals(mixed $other): bool
    {
        if ($this === $other) {
            return true;
        }

        if (!$other instanceof SeqInterface || !$other instanceof Traversable) {
            return false;
        }

        return LazySeqEquality::walkPairwise($this->getIterator(), $other, $this->equalizer);
    }

    public function hash(): int
    {
        if ($this->hashCache === null) {
            $this->hashCache = $this->hasher->orderedHash($this);
        }

        return $this->hashCache;
    }

    /**
     * Walks the lazy tail one cell at a time without forcing past the
     * elements the caller actually consumes.
     *
     * @return iterable<int, T>
     */
    private function walkTail(): iterable
    {
        $current = $this->rest;
        /** @phpstan-ignore instanceof.alwaysTrue */
        while ($current instanceof LazySeqInterface) {
            $head = $current->first();
            if ($head === null) {
                return;
            }

            /** @var T $head */
            yield $head;

            $next = $current->cdr();
            if (!$next instanceof LazySeqInterface) {
                return;
            }

            $current = $next;
        }
    }
}
