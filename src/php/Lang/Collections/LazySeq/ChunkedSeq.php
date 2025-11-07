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

use function array_slice;
use function count;
use function is_array;
use function is_object;

/**
 * A chunked lazy sequence that realizes elements in batches for better performance.
 * Instead of realizing one element at a time, ChunkedSeq realizes chunks of
 * elements (default 32) which significantly improves performance for most operations.
 *
 * @template T
 *
 * @implements LazySeqInterface<T>
 *
 * @extends AbstractType<LazySeqInterface<T>>
 */
final class ChunkedSeq extends AbstractType implements LazySeqInterface, Countable, IteratorAggregate
{
    /**
     * @param Chunk<T>                    $chunk The current chunk of realized values
     * @param callable|null               $fn    A thunk that produces the rest of the sequence
     * @param PersistentMapInterface|null $meta  Metadata for this sequence
     */
    public function __construct(
        private readonly HasherInterface $hasher,
        private readonly EqualizerInterface $equalizer,
        private readonly Chunk $chunk,
        private $fn,
        private readonly ?PersistentMapInterface $meta = null,
    ) {
    }

    /**
     * Creates a ChunkedSeq from a Generator, realizing elements in chunks.
     *
     * @template U
     *
     * @param Generator<int, U> $generator
     *
     * @return ChunkedSeq<U>|null
     */
    public static function fromGenerator(
        HasherInterface $hasher,
        EqualizerInterface $equalizer,
        Generator $generator,
        int $chunkSize = LazySeqConfig::CHUNK_SIZE,
        ?PersistentMapInterface $meta = null,
    ): ?self {
        $values = [];

        // Realize a chunk
        while ($generator->valid() && count($values) < $chunkSize) {
            $values[] = $generator->current();
            $generator->next();
        }

        if ($values === []) {
            return null;
        }

        $chunk = new Chunk($values);

        // Create thunk for the rest
        $fn = static fn (): ?ChunkedSeq => self::fromGenerator($hasher, $equalizer, $generator, $chunkSize);

        return new self($hasher, $equalizer, $chunk, $fn, $meta);
    }

    /**
     * Creates a ChunkedSeq from an array, chunking it for lazy evaluation.
     *
     * @template U
     *
     * @param array<int, U> $array
     *
     * @return ChunkedSeq<U>|null
     */
    public static function fromArray(
        HasherInterface $hasher,
        EqualizerInterface $equalizer,
        array $array,
        int $chunkSize = LazySeqConfig::CHUNK_SIZE,
        ?PersistentMapInterface $meta = null,
    ): ?self {
        if ($array === []) {
            return null;
        }

        // Take the first chunk
        $chunkValues = array_slice($array, 0, $chunkSize);
        $chunk = new Chunk($chunkValues);

        // Remaining elements
        $remaining = array_slice($array, $chunkSize);

        $fn = $remaining === []
            ? null
            : static fn (): ?ChunkedSeq => self::fromArray($hasher, $equalizer, $remaining, $chunkSize);

        return new self($hasher, $equalizer, $chunk, $fn, $meta);
    }

    public function isRealized(): bool
    {
        // ChunkedSeq is always partially realized (at least the first chunk)
        return true;
    }

    /**
     * @return T|null
     */
    public function first(): mixed
    {
        return $this->chunk->first();
    }

    /**
     * @return LazySeqInterface<T>|null
     */
    public function cdr(): self|null|LazySeqInterface|LazySeq
    {
        if ($this->chunk->count() > 1) {
            // Still have elements in current chunk
            return new self(
                $this->hasher,
                $this->equalizer,
                $this->chunk->drop(1),
                $this->fn,
                $this->meta,
            );
        }

        // Need to realize the next chunk
        if ($this->fn === null) {
            return null;
        }

        $fn = $this->fn;
        $result = $fn();

        // If result is a LazySeq, return it; otherwise wrap it
        if ($result instanceof LazySeqInterface) {
            return $result;
        }

        if ($result instanceof SeqInterface) {
            return LazySeq::fromIterable(
                $this->hasher,
                $this->equalizer,
                $result->toArray(),
            );
        }

        return null;
    }

    /**
     * @return LazySeqInterface<T>
     */
    public function rest(): LazySeq|LazySeqInterface
    {
        $cdr = $this->cdr();

        if (!$cdr instanceof LazySeqInterface) {
            // Return empty LazySeq
            return new LazySeq($this->hasher, $this->equalizer, static fn (): null => null);
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
        // Prepend to the current chunk
        $newValues = array_merge([$x], $this->chunk->toArray());
        $newChunk = new Chunk($newValues);

        return new self($this->hasher, $this->equalizer, $newChunk, $this->fn, $this->meta);
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

        // Use the iterator which properly handles all chunks
        foreach ($this as $value) {
            $result[] = $value;
        }

        return $result;
    }

    public function getIterator(): Traversable
    {
        $current = $this;

        // Iteratively process chunks to avoid deep recursion
        /** @psalm-suppress RedundantCondition */
        /** @phpstan-ignore instanceof.alwaysTrue */
        while ($current instanceof self) {
            // Yield elements from current chunk
            foreach ($current->chunk->toArray() as $value) {
                yield $value;
            }

            // Move to next chunk
            if ($current->fn === null) {
                break;
            }

            $fn = $current->fn;
            $rest = $fn();

            // Continue if rest is a ChunkedSeq
            if ($rest instanceof self) {
                $current = $rest;
            } elseif ($rest !== null && is_iterable($rest)) {
                // Handle other iterables
                foreach ($rest as $value) {
                    yield $value;
                }

                break;
            } else {
                break;
            }
        }
    }

    public function getMeta(): ?PersistentMapInterface
    {
        return $this->meta;
    }

    public function withMeta(?PersistentMapInterface $meta): static
    {
        return new self($this->hasher, $this->equalizer, $this->chunk, $this->fn, $meta);
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
        // Check if other is a sequence or indexable collection
        if ($other instanceof SeqInterface) {
            return $this->toArray() === $other->toArray();
        }

        // Check if it has toArray method (like vectors)
        if (is_object($other) && method_exists($other, 'toArray')) {
            return $this->toArray() === $other->toArray();
        }

        // Direct array comparison
        if (is_array($other)) {
            return $this->toArray() === $other;
        }

        return false;
    }
}
