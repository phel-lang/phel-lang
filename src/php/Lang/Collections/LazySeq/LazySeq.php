<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\LazySeq;

use Countable;
use Generator;
use Iterator;
use IteratorAggregate;
use Phel\Lang\AbstractType;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\EqualizerInterface;
use Phel\Lang\HasherInterface;
use Phel\Lang\SeqInterface;

use Traversable;

use function count;
use function is_array;
use function is_object;

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
    /**
     * Keeps the rolling hash accumulator inside a 32-bit unsigned range so
     * `31 * $hash` never exceeds PHP_INT_MAX and silently promotes to float
     * (which would throw a TypeError on the `: int` return for long
     * sequences). Kept in lockstep with vector, list, queue and map hashing.
     */
    private const int HASH_MASK = 0xFFFFFFFF;

    /** @var callable|null The thunk that produces the sequence (null once realized) */
    private $fn;

    /** @var SeqInterface<mixed, SeqInterface<mixed, LazySeqInterface<mixed>>>|null The realized sequence (null until computed) */
    private $realized;

    /**
     * @param callable                                  $fn   A thunk (nullary function) that returns a sequence or null
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
     * Creates a LazySeq from a Generator.
     *
     * @template U
     *
     * @param Generator<int, U>                         $generator
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     *
     * @return self<U>
     */
    public static function fromGenerator(
        HasherInterface $hasher,
        EqualizerInterface $equalizer,
        Generator $generator,
        ?PersistentMapInterface $meta = null,
    ): self {
        /** @var self<U> $result */
        $result = new self(
            $hasher,
            $equalizer,
            static function () use ($generator, $hasher, $equalizer): ?LazySeqInterface {
                if (!$generator->valid()) {
                    return null;
                }

                $value = $generator->current();
                $generator->next();

                return new self(
                    $hasher,
                    $equalizer,
                    static fn(): LazySeq => self::fromGenerator($hasher, $equalizer, $generator),
                )->cons($value);
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
     * @template U
     *
     * @param Traversable<mixed, U>                     $traversable
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     *
     * @return self<U>
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
     * Creates a LazySeq from any iterable.
     *
     * @template U
     *
     * @param iterable<U>                               $iterable
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     *
     * @return self<U>|null
     */
    public static function fromIterable(
        HasherInterface $hasher,
        EqualizerInterface $equalizer,
        iterable $iterable,
        ?PersistentMapInterface $meta = null,
    ): ?self {
        if (is_array($iterable)) {
            /** @var self<U>|null $result */
            $result = self::fromArray($hasher, $equalizer, $iterable, $meta);

            return $result;
        }

        if ($iterable instanceof Generator) {
            /** @var self<U> $result */
            $result = self::fromGenerator($hasher, $equalizer, $iterable, $meta);

            return $result;
        }

        // Convert to array for other iterables
        $array = [];
        foreach ($iterable as $item) {
            $array[] = $item;
        }

        /** @var self<U>|null $result */
        $result = self::fromArray($hasher, $equalizer, $array, $meta);

        return $result;
    }

    /**
     * Creates a LazySeq from an array.
     *
     * @template U
     *
     * @param array<int, U>                             $array
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     *
     * @return self<U>|null
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

        /** @var self<U> $result */
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
        $seq = $this->realize();

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
        $seq = $this->realize();

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

        /** @psalm-suppress RedundantCondition */
        /** @phpstan-ignore instanceof.alwaysTrue */
        while ($seq instanceof self) {
            // `realize() === null` is the only reliable "empty" signal;
            // `first() === null` is ambiguous because the user may have
            // stored `nil` as a legitimate value. Empty `Countable`
            // collections (e.g. `(lazy-seq [])`) also count as empty.
            $realized = $seq->realize();
            if (!$realized instanceof SeqInterface) {
                break;
            }

            if ($realized instanceof Countable && count($realized) === 0) {
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

        while ($seq instanceof self) {
            // See `toArray()`: realize is the only nil-safe empty check.
            $realized = $seq->realize();
            if (!$realized instanceof SeqInterface) {
                return;
            }

            if ($realized instanceof Countable && count($realized) === 0) {
                return;
            }

            yield $realized->first();

            $next = $seq->cdr();
            if (!$next instanceof LazySeqInterface) {
                return;
            }

            $seq = $next instanceof self ? $next : null;
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
        $hash = 1;
        foreach ($this as $value) {
            $hash = (31 * $hash + $this->hasher->hash($value)) & self::HASH_MASK;
        }

        return $hash;
    }

    public function equals(mixed $other): bool
    {
        // Short-circuit on identity — avoids realizing infinite lazy-seqs when comparing against self.
        if ($this === $other) {
            return true;
        }

        $otherIter = $this->lazyIteratorFor($other);
        if (!$otherIter instanceof Generator) {
            return false;
        }

        return $this->walkPairwise($this->getIterator(), $otherIter, $this->equalizer);
    }

    /**
     * Converts an arbitrary value into a lazy iterator, or returns `null`
     * when the value is not sequence-like. Used by `equals` to compare
     * element-by-element without realizing infinite lazy sequences.
     */
    /**
     * @return Generator<int, mixed>|null
     */
    private function lazyIteratorFor(mixed $value): ?Generator
    {
        if ($value instanceof Traversable) {
            return (static function () use ($value): Generator {
                foreach ($value as $v) {
                    yield $v;
                }
            })();
        }

        if (is_array($value)) {
            return (static function () use ($value): Generator {
                foreach ($value as $v) {
                    yield $v;
                }
            })();
        }

        if (is_object($value) && method_exists($value, 'toArray')) {
            $array = $value->toArray();
            return (static function () use ($array): Generator {
                foreach ($array as $v) {
                    yield $v;
                }
            })();
        }

        return null;
    }

    /**
     * Walks two iterators in lockstep, comparing element-by-element via
     * the equalizer. Returns `false` on the first divergence (either a
     * length mismatch or an unequal element) and `true` only if both
     * iterators exhaust at the same point.
     *
     * Running this against two infinite sequences loops forever, matching
     * Clojure's behavior — it is the caller's responsibility to avoid
     * comparing two infinite sequences for equality.
     */
    /**
     * @param Traversable<mixed> $left
     * @param Traversable<mixed> $right
     */
    private function walkPairwise(Traversable $left, Traversable $right, EqualizerInterface $equalizer): bool
    {
        $leftIter = $this->asIterator($left);
        $rightIter = $this->asIterator($right);

        $leftIter->rewind();
        $rightIter->rewind();

        while (true) {
            $leftValid = $leftIter->valid();
            $rightValid = $rightIter->valid();
            if ($leftValid !== $rightValid) {
                return false;
            }

            if (!$leftValid) {
                return true;
            }

            if (!$equalizer->equals($leftIter->current(), $rightIter->current())) {
                return false;
            }

            $leftIter->next();
            $rightIter->next();
        }
    }

    /**
     * Normalizes a `Traversable` into an `\Iterator`. Generators are
     * already iterators, but an `IteratorAggregate` returns a nested
     * Traversable that must be unwrapped before `valid`/`current`/`next`
     * can be called directly.
     */
    /**
     * @param Traversable<mixed> $traversable
     *
     * @return Iterator<mixed, mixed>
     */
    private function asIterator(Traversable $traversable): Iterator
    {
        while ($traversable instanceof IteratorAggregate) {
            $traversable = $traversable->getIterator();
        }

        /** @var Iterator $traversable */
        return $traversable;
    }

    /**
     * Realizes this lazy sequence if not already realized.
     * Uses iterative approach to avoid stack overflow.
     */
    /**
     * @return SeqInterface<mixed, SeqInterface<mixed, LazySeqInterface<mixed>>>|null
     */
    private function realize(): ?SeqInterface
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
            // Get the nested LazySeq's function and call it directly
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
