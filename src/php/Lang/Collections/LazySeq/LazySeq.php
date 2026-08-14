<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\LazySeq;

use Countable;
use Generator;
use IteratorAggregate;
use Phel\Lang\AbstractType;
use Phel\Lang\Collections\Exceptions\NotASeqException;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\EqualizerInterface;
use Phel\Lang\HasherInterface;
use Phel\Lang\Seq;
use Phel\Lang\SeqInterface;

use Traversable;

use function count;
use function is_array;

/**
 * A lazy sequence that defers computation until values are actually needed.
 * Once realized, values are cached for subsequent access.
 *
 * @template T
 *
 * @implements LazySeqInterface<T>
 * @implements IteratorAggregate<int, T>
 *
 * @extends AbstractType<LazySeqInterface<T>>
 */
final class LazySeq extends AbstractType implements LazySeqInterface, Countable, IteratorAggregate
{
    /** @var (callable(): mixed)|null The thunk that produces the sequence (null once realized) */
    private $fn;

    /**
     * Whatever the thunk returned (null until computed). Deliberately as wide
     * as the thunk itself: `(lazy-seq 5)` stores a raw `int` here, and
     * {@see self::realizeSeq()} is the single place that narrows it to a seq.
     */
    private mixed $realized = null;

    /**
     * @param callable(): mixed                         $fn   A thunk (nullary function) that returns a sequence or null
     * @param PersistentMapInterface<mixed, mixed>|null $meta Metadata for this sequence
     */
    public function __construct(
        private readonly HasherInterface $hasher,
        private readonly EqualizerInterface $equalizer,
        callable $fn,
        private ?PersistentMapInterface $meta = null,
    ) {
        $this->fn = $fn;
    }

    /**
     * @template TValue
     *
     * @param Generator<int, TValue>                    $generator
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     *
     * @return self<TValue>
     */
    public static function fromGenerator(
        HasherInterface $hasher,
        EqualizerInterface $equalizer,
        Generator $generator,
        ?PersistentMapInterface $meta = null,
    ): self {
        /** @var self<TValue> $result */
        $result = new self(
            $hasher,
            $equalizer,
            // One `Cons` over a fresh lazy tail, not `new self(...)->cons(...)`.
            // `cons()` wraps its result in another `LazySeq` holding another
            // thunk, so that spelling allocated two `LazySeq`s and two closures
            // per element and put a second thunk call between the caller and
            // the value. `fromGenerator` does not pull from the generator, so
            // building the tail here is still lazy. Realizing 64 elements of a
            // `concat` went from 95.9μs to 61.4μs.
            static function () use ($generator, $hasher, $equalizer): ?SeqInterface {
                if (!$generator->valid()) {
                    return null;
                }

                $value = $generator->current();
                $generator->next();

                return new Cons(
                    $hasher,
                    $equalizer,
                    $value,
                    self::fromGenerator($hasher, $equalizer, $generator),
                );
            },
            $meta,
        );

        return $result;
    }

    /**
     * Creates a LazySeq from any PHP Traversable (Iterator, Generator,
     * IteratorAggregate, ...), pulling one element at a time.
     *
     * Unlike {@see fromIterable}, a non-array, non-Generator Traversable is
     * NOT materialised eagerly, so a large or infinite cursor (DB result,
     * stream reader, paginated API) streams lazily.
     *
     * @template TValue
     *
     * @param Traversable<mixed, TValue>                $traversable
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     *
     * @return self<TValue>
     */
    public static function fromTraversable(
        HasherInterface $hasher,
        EqualizerInterface $equalizer,
        Traversable $traversable,
        ?PersistentMapInterface $meta = null,
    ): self {
        $generator = $traversable instanceof Generator
            ? $traversable
            : (static function () use ($traversable): Generator {
                yield from $traversable;
            })();

        return self::fromGenerator($hasher, $equalizer, $generator, $meta);
    }

    /**
     * @template TValue
     *
     * @param iterable<TValue>                          $iterable
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     *
     * @return self<TValue>|null
     */
    public static function fromIterable(
        HasherInterface $hasher,
        EqualizerInterface $equalizer,
        iterable $iterable,
        ?PersistentMapInterface $meta = null,
    ): ?self {
        if (is_array($iterable)) {
            /** @var self<TValue>|null $result */
            $result = self::fromArray($hasher, $equalizer, $iterable, $meta);

            return $result;
        }

        if ($iterable instanceof Generator) {
            /** @var self<TValue> $result */
            $result = self::fromGenerator($hasher, $equalizer, $iterable, $meta);

            return $result;
        }

        // Convert to array for other iterables
        $array = [];
        foreach ($iterable as $item) {
            $array[] = $item;
        }

        /** @var self<TValue>|null $result */
        $result = self::fromArray($hasher, $equalizer, $array, $meta);

        return $result;
    }

    /**
     * @template TValue
     *
     * @param array<int, TValue>                        $array
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     *
     * @return self<TValue>|null
     */
    public static function fromArray(
        HasherInterface $hasher,
        EqualizerInterface $equalizer,
        array $array,
        ?PersistentMapInterface $meta = null,
    ): ?self {
        if ($array === []) {
            return null;
        }

        $first = array_shift($array);

        /** @var self<TValue> $result */
        $result = new self(
            $hasher,
            $equalizer,
            static fn(): ?LazySeq => self::fromArray($hasher, $equalizer, $array),
            $meta,
        )->cons($first);

        return $result;
    }

    /**
     * Returns a realized empty `LazySeq` whose thunk yields `null`. Used
     * as a sentinel tail when no further elements remain.
     *
     * @return self<mixed>
     */
    public static function empty(HasherInterface $hasher, EqualizerInterface $equalizer): self
    {
        return new self($hasher, $equalizer, static fn(): null => null);
    }

    public function isRealized(): bool
    {
        return $this->fn === null;
    }

    /**
     * @return T|null
     */
    public function first()
    {
        $seq = $this->realizeSeq();

        if (!$seq instanceof SeqInterface) {
            return null;
        }

        return $seq->first();
    }

    /**
     * Returns the tail seq without forcing the head of that tail. Callers
     * who need to know whether the tail is empty must probe with `first()`
     * or use `nextSeq()`.
     *
     * @return LazySeqInterface<T>|null
     */
    public function cdr(): LazySeqInterface|self|null
    {
        $seq = $this->realizeSeq();

        if (!$seq instanceof SeqInterface) {
            return null;
        }

        $rest = $seq->cdr();

        if ($rest === null) {
            return null;
        }

        if ($rest instanceof LazySeqInterface) {
            return $rest;
        }

        /** @var self<T> $result */
        $result = new self($this->hasher, $this->equalizer, static fn(): SeqInterface => $rest);

        return $result;
    }

    /**
     * Mirrors Clojure's `(next s)` semantics: returns a realized cons cell
     * (`Cons`) holding the next head and a lazy tail, or `null` when
     * the tail is exhausted. The returned value is never a
     * `LazySeqInterface`.
     *
     * @return Cons<mixed>|null
     */
    public function nextSeq(): ?Cons
    {
        return Cons::fromCdr($this->hasher, $this->equalizer, $this->cdr());
    }

    /**
     * @return LazySeqInterface<T>
     */
    public function rest(): self|LazySeqInterface
    {
        return $this->cdr() ?? self::empty($this->hasher, $this->equalizer);
    }

    /**
     * @param T $x
     *
     * @return self<T>
     */
    public function cons($x): self
    {
        $hasher = $this->hasher;
        $equalizer = $this->equalizer;
        $self = $this;

        /** @var self<T> $result */
        $result = new self(
            $hasher,
            $equalizer,
            static fn(): Cons => new Cons($hasher, $equalizer, $x, $self),
            $this->meta,
        );

        return $result;
    }

    public function count(): int
    {
        // Warning: This realizes the entire sequence!
        return count($this->toArray());
    }

    /**
     * @return array<int, T>
     */
    public function toArray(): array
    {
        $result = [];
        $seq = $this;

        // `$seq` only ever advances to another `self` (every other tail shape
        // breaks below), so the walk is bounded by the breaks, not by a guard.
        while (true) {
            // `realize() === null` is the only reliable "empty" signal;
            // `first() === null` is ambiguous because the user may have
            // stored `nil` as a legitimate value. Empty `Countable`
            // collections (e.g. `(lazy-seq [])`) also count as empty.
            $realized = $seq->realizeSeq();
            if (!$realized instanceof SeqInterface) {
                break;
            }

            if ($this->isEmptySeq($realized)) {
                break;
            }

            $result[] = $realized->first();

            $next = $seq->cdr();
            if (!$next instanceof LazySeqInterface) {
                break;
            }

            // Handle both LazySeq and other SeqInterface implementations
            if ($next instanceof self) {
                $seq = $next;
            } elseif ($next instanceof SeqInterface) {
                // Realize remaining non-lazy sequence
                $remaining = $next->toArray();
                $result = array_merge($result, $remaining);
                break;
            } else {
                break;
            }
        }

        return $result;
    }

    /**
     * @return Traversable<int, T>
     */
    public function getIterator(): Traversable
    {
        $seq = $this;

        // `$seq` only ever advances to another `self`; every other tail shape
        // returns below, so the walk has no condition of its own.
        while (true) {
            // See `toArray()`: realize is the only nil-safe empty check.
            $realized = $seq->realizeSeq();
            if (!$realized instanceof SeqInterface) {
                return;
            }

            if ($this->isEmptySeq($realized)) {
                return;
            }

            yield $realized->first();

            $next = $seq->cdr();
            if (!$next instanceof LazySeqInterface) {
                return;
            }

            if (!$next instanceof self) {
                // A lazy tail that is not a `LazySeq` (today: `ChunkedSeq`,
                // which everything built on `lazy-seq-from-generator` returns)
                // cannot drive this loop, which walks `LazySeq` links. Hand off
                // to its own iterator, itself a generator, so this stays lazy.
                // Ending the walk here instead silently dropped the rest while
                // `count` still reported the full length (#3020); `toArray()`
                // merges the same tail, and the two must not disagree.
                //
                // Yield each value rather than `yield from`: that keeps the
                // keys contiguous with the ones yielded above, which a caller
                // using `iterator_to_array()` with preserved keys relies on.
                foreach ($next as $value) {
                    yield $value;
                }

                return;
            }

            $seq = $next;
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

    public function hash(): int
    {
        // Realize and hash the sequence (similar to PersistentList)
        return $this->hasher->orderedHash($this);
    }

    public function equals(mixed $other): bool
    {
        // Short-circuit on identity — avoids realizing infinite lazy-seqs when comparing against self.
        if ($this === $other) {
            return true;
        }

        $otherIter = LazySeqEquality::iteratorFor($other);
        if (!$otherIter instanceof Generator) {
            return false;
        }

        return LazySeqEquality::walkPairwise($this->getIterator(), $otherIter, $this->equalizer);
    }

    /**
     * Whether a realized seq holds no elements, without realizing it.
     *
     * `count()` cannot answer this for a lazy seq: both `LazySeq::count()` and
     * `ChunkedSeq::count()` compute it by realizing the whole sequence, and
     * each says so in its own comment. Asking it here materialized every
     * element of a chunked tail before the first one was yielded, and over an
     * unbounded source never returned at all (#3023).
     *
     * `first() === null` cannot answer it either, because nil is a legitimate
     * element: `(lazy-seq [nil])` holds one, not zero.
     *
     * So: a Countable that is not lazy knows its size in O(1) and is asked
     * directly, and a lazy one is probed with {@see Seq::isEmpty()}, which
     * pulls at most one element and therefore distinguishes "empty" from
     * "first element is nil" without draining anything.
     */
    private function isEmptySeq(mixed $seq): bool
    {
        if ($seq instanceof Countable && !$seq instanceof LazySeqInterface) {
            return count($seq) === 0;
        }

        if ($seq instanceof Traversable) {
            return Seq::isEmpty($seq);
        }

        return false;
    }

    /**
     * Realizes this lazy sequence and narrows the result to a seq.
     *
     * A thunk may hand back anything at all, since `(lazy-seq <body>)` splices
     * the user's body verbatim, so the narrowing lives here rather than in the
     * property type.
     *
     * @throws NotASeqException when the body produced a non-seq value
     *
     * @return SeqInterface<mixed, SeqInterface<mixed, LazySeqInterface<mixed>>>|null
     */
    private function realizeSeq(): ?SeqInterface
    {
        $realized = $this->realize();

        if ($realized === null || $realized instanceof SeqInterface) {
            /** @var SeqInterface<mixed, SeqInterface<mixed, LazySeqInterface<mixed>>>|null $realized */
            return $realized;
        }

        throw NotASeqException::forValue($realized);
    }

    /**
     * Realizes this lazy sequence if not already realized, caching whatever the
     * thunk produced. Uses an iterative approach to avoid stack overflow.
     *
     * The raw value is cached even when it is not a seq, so that repeated
     * access keeps reporting the same failure instead of turning into an empty
     * sequence after the first call.
     */
    private function realize(): mixed
    {
        if ($this->fn === null) {
            return $this->realized;
        }

        $fn = $this->fn;
        $this->fn = null; // Clear the function to allow garbage collection

        $result = $fn();

        // Iteratively realize nested LazySeqs to avoid recursion
        // This handles cases where a LazySeq's thunk returns another unrealized LazySeq
        while ($result instanceof self && !$result->isRealized()) {
            if ($result->fn !== null) {
                $nestedFn = $result->fn;
                $result->fn = null;
                $result = $nestedFn();
            } else {
                break;
            }
        }

        $this->realized = $result;

        return $result;
    }
}
