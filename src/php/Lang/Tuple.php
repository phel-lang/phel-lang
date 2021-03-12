<?php

declare(strict_types=1);

namespace Phel\Lang;

use ArrayAccess;
use Countable;
use InvalidArgumentException;
use Iterator;
use Phel\Printer\Printer;

/**
 * @template T
 * @template-implements ArrayAccess<int, mixed>
 * @template-implements Iterator<int, mixed>
 * @template-implements SeqInterface<T, Tuple<T>>
 */
final class Tuple extends AbstractType implements
    ArrayAccess,
    Countable,
    Iterator,
    SliceInterface,
    ConsInterface,
    SeqInterface,
    PushInterface,
    ConcatInterface
{
    use IteratorComparatorTrait;

    /** @var array<int, mixed> */
    private array $data;
    private bool $usingBracket;

    /**
     * @param array<int, mixed> $data
     * @param bool $usingBracket true if this is bracket tuple
     */
    public function __construct(array $data, bool $usingBracket = false)
    {
        $this->data = $data;
        $this->usingBracket = $usingBracket;
    }

    /**
     * Create a new Tuple.
     *
     * @param mixed ...$values
     */
    public static function create(...$values): Tuple
    {
        return new Tuple($values);
    }

    /**
     * Create a new bracket Tuple.
     *
     * @param mixed ...$values
     */
    public static function createBracket(...$values): Tuple
    {
        return new Tuple($values, true);
    }

    public function offsetSet($offset, $value): void
    {
        throw new \InvalidArgumentException('Calling offsetSet is not supported on Tuples since they are immutable');
    }

    public function offsetExists($offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetUnset($offset): void
    {
        throw new \InvalidArgumentException('Calling offsetUnset is not supported on Tuples since they are immutable');
    }

    /**
     * @param int $offset
     *
     * @return mixed|null
     */
    public function offsetGet($offset)
    {
        return $this->data[$offset] ?? null;
    }

    public function count(): int
    {
        return count($this->data);
    }

    public function isUsingBracket(): bool
    {
        return $this->usingBracket;
    }

    /**
     * @return mixed
     */
    public function current()
    {
        return current($this->data);
    }

    public function key()
    {
        return (int) key($this->data);
    }

    public function next(): void
    {
        next($this->data);
    }

    public function rewind(): void
    {
        reset($this->data);
    }

    public function valid(): bool
    {
        return key($this->data) !== null;
    }

    /**
     * Update a tuple value. For internal use only.
     *
     * @param int $offset The index to update
     * @param mixed $value The value to set on $index
     *
     * @return Tuple A copy of the tuple with an update value
     *
     * @internal
     */
    public function update(int $offset, $value): Tuple
    {
        if ($offset < 0 || $offset > count($this->data)) {
            throw new InvalidArgumentException('Index out of bounds: ' . $offset . ' [0,' . count($this->data) . ']');
        }

        if (is_null($value)) {
            $newData = $this->data;
            unset($newData[$offset]);
            $res = new Tuple(array_values($newData), $this->isUsingBracket()); // reindex
        } elseif ($offset === count($this->data)) {
            $res = new Tuple([...$this->data, $value], $this->isUsingBracket());
        } else {
            $newData = $this->data;
            $newData[$offset] = $value;
            $res = new Tuple($newData, $this->isUsingBracket());
        }

        $res->copyLocationFrom($this);

        return $res;
    }

    public function slice(int $offset = 0, ?int $length = null): SliceInterface
    {
        return new Tuple(
            array_slice($this->data, $offset, $length),
            $this->isUsingBracket()
        );
    }

    public function cons($x): ConsInterface
    {
        return new Tuple([$x, ...$this->data], $this->isUsingBracket());
    }

    public function hash(): int
    {
        return crc32(spl_object_hash($this));
    }

    public function equals($other): bool
    {
        // Should be the same type
        if (!($other instanceof Tuple)) {
            return false;
        }

        // Should have the same length
        if (count($this) !== count($other)) {
            return false;
        }

        // Should have the same brackets
        if ($this->isUsingBracket() !== $other->isUsingBracket()) {
            return false;
        }

        return $this->hasSameKeysAndValues($other);
    }

    public function first()
    {
        if (count($this->data) > 0) {
            return $this->data[0];
        }

        return null;
    }

    public function cdr()
    {
        if ($this->count() <= 1) {
            return null;
        }

        return new Tuple(array_slice($this->data, 1), $this->isUsingBracket());
    }

    public function rest()
    {
        return new Tuple(array_slice($this->data, 1), $this->isUsingBracket());
    }

    public function push($x): PushInterface
    {
        return new Tuple([...$this->data, $x], $this->isUsingBracket());
    }

    public function concat($xs): ConcatInterface
    {
        $newData = $this->data;
        /** @var mixed $x */
        foreach ($xs as $x) {
            $newData[] = $x;
        }

        return new Tuple($newData, $this->isUsingBracket());
    }

    /**
     * @internal
     */
    public function toArray(): array
    {
        return $this->data;
    }

    public function __toString(): string
    {
        return Printer::readable()->print($this);
    }

    public function hasEvenNumberOfParams(): bool
    {
        return $this->count() % 2 === 0;
    }
}
