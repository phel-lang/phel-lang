<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\LazySeq;

use Phel\Lang\Collections\Exceptions\IndexOutOfBoundsException;

use function array_slice;
use function count;
use function sprintf;

/**
 * Represents a fixed-size chunk of realized values.
 * Chunks are used to improve performance by realizing elements in batches
 * instead of one at a time.
 *
 * @template T
 */
final readonly class Chunk
{
    /**
     * @param array<int, T> $values The array of values in this chunk
     * @param int           $offset The offset within the chunk (for dropped elements)
     */
    public function __construct(
        private array $values,
        private int $offset = 0,
    ) {
    }

    /**
     * Gets the value at the specified index within this chunk.
     *
     * @throws IndexOutOfBoundsException
     *
     * @return T
     */
    public function get(int $index): mixed
    {
        $actualIndex = $this->offset + $index;
        if (!isset($this->values[$actualIndex])) {
            throw new IndexOutOfBoundsException(sprintf('Index %d is out of bounds', $index));
        }

        return $this->values[$actualIndex];
    }

    /**
     * Returns the number of elements remaining in this chunk.
     */
    public function count(): int
    {
        return count($this->values) - $this->offset;
    }

    /**
     * Creates a new chunk with the first n elements dropped.
     */
    public function drop(int $n): self
    {
        $newOffset = $this->offset + $n;
        if ($newOffset >= count($this->values)) {
            return new self([], 0);
        }

        return new self($this->values, $newOffset);
    }

    /**
     * Returns the first element in this chunk.
     *
     * @return T|null
     */
    public function first(): mixed
    {
        if ($this->count() === 0) {
            return null;
        }

        return $this->values[$this->offset];
    }

    /**
     * Converts this chunk to a PHP array.
     *
     * @return array<int, T>
     */
    public function toArray(): array
    {
        if ($this->offset === 0) {
            return $this->values;
        }

        return array_slice($this->values, $this->offset);
    }
}
