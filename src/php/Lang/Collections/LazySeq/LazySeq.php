<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\LazySeq;

use Countable;
use Generator;
use IteratorAggregate;
use Phel\Lang\AbstractType;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\EqualizerInterface;
use Phel\Lang\HasherInterface;
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
 *
 * @extends AbstractType<LazySeqInterface<T>>
 */
final class LazySeq extends AbstractType implements LazySeqInterface, Countable, IteratorAggregate
{
    /** @var callable|null The thunk that produces the sequence (null once realized) */
    private $fn;

    /** @var SeqInterface|null The realized sequence (null until computed) */
    private $realized;

    /**
     * @param callable                    $fn   A thunk (nullary function) that returns a sequence or null
     * @param PersistentMapInterface|null $meta Metadata for this sequence
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
     * @param Generator<int, U> $generator
     *
     * @return self<U>
     */
    public static function fromGenerator(
        HasherInterface $hasher,
        EqualizerInterface $equalizer,
        Generator $generator,
        ?PersistentMapInterface $meta = null,
    ): self {
        return new self(
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
                    static fn (): LazySeq => self::fromGenerator($hasher, $equalizer, $generator),
                    null,
                )->cons($value);
            },
            $meta,
        );
    }

    /**
     * Creates a LazySeq from any iterable.
     *
     * @template U
     *
     * @param iterable<U> $iterable
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
            return self::fromArray($hasher, $equalizer, $iterable, $meta);
        }

        if ($iterable instanceof Generator) {
            return self::fromGenerator($hasher, $equalizer, $iterable, $meta);
        }

        // Convert to array for other iterables
        $array = [];
        foreach ($iterable as $item) {
            $array[] = $item;
        }

        return self::fromArray($hasher, $equalizer, $array, $meta);
    }

    /**
     * Creates a LazySeq from an array.
     *
     * @template U
     *
     * @param array<int, U> $array
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

        return new self(
            $hasher,
            $equalizer,
            static fn (): ?LazySeq => self::fromArray($hasher, $equalizer, $array),
            $meta,
        )->cons($first);
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
     * @return LazySeqInterface<T>|null
     */
    public function cdr(): null|LazySeqInterface|self
    {
        $seq = $this->realize();

        if (!$seq instanceof SeqInterface) {
            return null;
        }

        $rest = $seq->cdr();

        if ($rest === null) {
            return null;
        }

        // Check if rest is empty
        if ($rest instanceof SeqInterface) {
            $first = $rest->first();
            if ($first === null) {
                // Empty sequence
                return null;
            }
        }

        // Wrap the rest in a LazySeq to maintain laziness
        if ($rest instanceof LazySeqInterface) {
            return $rest;
        }

        /** @phpstan-ignore return.type */
        return new self($this->hasher, $this->equalizer, static fn (): SeqInterface => $rest);
    }

    /**
     * @return LazySeqInterface<T>
     */
    public function rest(): self|LazySeqInterface
    {
        $cdr = $this->cdr();

        if (!$cdr instanceof LazySeqInterface) {
            // Return empty LazySeq
            return new self($this->hasher, $this->equalizer, static fn (): null => null);
        }

        return $cdr;
    }

    /**
     * @param T $x
     *
     * @return self<T>
     */
    public function cons($x): self
    {
        $self = $this;

        return new self(
            $this->hasher,
            $this->equalizer,
            /** @psalm-suppress InvalidReturnType, InvalidReturnStatement */
            static fn (): SeqInterface =>
                // Create a simple cons cell
                new readonly class($x, $self) implements SeqInterface {
                    public function __construct(
                        private mixed $first,
                        private LazySeqInterface $rest,
                    ) {
                    }

                    public function first()
                    {
                        return $this->first;
                    }

                    public function cdr(): LazySeqInterface
                    {
                        return $this->rest;
                    }

                    public function rest(): LazySeqInterface
                    {
                        return $this->rest;
                    }

                    public function toArray(): array
                    {
                        // Iterative implementation to avoid stack overflow
                        $result = [$this->first];
                        $current = $this->rest;

                        // Walk through the sequence iteratively
                        /** @phpstan-ignore instanceof.alwaysTrue */
                        while ($current instanceof LazySeqInterface) {
                            $realized = $current->first();
                            if ($realized === null) {
                                break;
                            }

                            $result[] = $realized;

                            $next = $current->cdr();
                            if ($next === null) {
                                break;
                            }

                            $current = $next;
                        }

                        return $result;
                    }
                },
            $this->meta,
        );
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
            $first = $seq->first();
            if ($first !== null) {
                $result[] = $first;
            }

            $next = $seq->cdr();
            if (!$next instanceof LazySeqInterface) {
                break;
            }

            // Handle both LazySeq and other SeqInterface implementations
            if ($next instanceof self) {
                $seq = $next;
                /** @phpstan-ignore instanceof.alwaysTrue */
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

    public function getIterator(): Traversable
    {
        $seq = $this;

        while ($seq instanceof self) {
            $first = $seq->first();
            if ($first !== null) {
                yield $first;
            }

            $next = $seq->cdr();
            if (!$next instanceof LazySeqInterface) {
                break;
            }

            $seq = $next instanceof self ? $next : null;
        }
    }

    public function getMeta(): ?PersistentMapInterface
    {
        return $this->meta;
    }

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
            $hash = 31 * $hash + $this->hasher->hash($value);
        }

        return $hash;
    }

    public function equals(mixed $other): bool
    {
        if (!($other instanceof SeqInterface)) {
            return false;
        }

        // Convert to arrays and compare
        return $this->toArray() === $other->toArray();
    }

    /**
     * Realizes this lazy sequence if not already realized.
     * Uses iterative approach to avoid stack overflow.
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
