<?php

declare(strict_types=1);

namespace Phel\Lang;

use ArrayAccess;
use Countable;
use InvalidArgumentException;
use Iterator;

class Tuple extends AbstractType implements ArrayAccess, Countable, Iterator, ISlice, ICons, ICdr, IRest, IPush, IConcat
{
    use HashableTrait;
    use PrintableTrait;
    use CountableTrait;
    use IterableTrait;

    private array $data;

    private bool $usingBracket;

    /**
     * @param array $data A list of values
     * @param bool $usingBracket True if this is bracket tuple.
     */
    public function __construct(array $data, bool $usingBracket = false)
    {
        $this->data = $data;
        $this->usingBracket = $usingBracket;
    }

    /**
     * Create a new Tuple.
     *
     * @param AbstractType|scalar|null ...$values The values
     */
    public static function create(...$values): Tuple
    {
        return new Tuple($values);
    }

    /**
     * Create a new bracket Tuple.
     *
     * @param AbstractType|scalar|null ...$values The values
     */
    public static function createBracket(...$values): Tuple
    {
        return new Tuple($values, true);
    }

    public function offsetSet($offset, $value)
    {
        throw new \InvalidArgumentException('Calling offsetSet is not supported on Tuples since they are immutable');
    }

    public function offsetExists($offset)
    {
        return isset($this->data[$offset]);
    }

    public function offsetUnset($offset)
    {
        throw new \InvalidArgumentException('Calling offsetUnset is not supported on Tuples since they are immutable');
    }

    /**
     * @return mixed|null
     */
    public function offsetGet($offset)
    {
        return $this->data[$offset] ?? null;
    }

    public function isUsingBracket(): bool
    {
        return $this->usingBracket;
    }

    /**
     * Update a tuple value. For internal use only.
     *
     * @param int $index The index to update
     * @param mixed $value The value to set on $index
     *
     * @return Tuple A copy of the tuple with an update value
     * @internal
     */
    public function update(int $offset, $value): Tuple
    {
        if ($offset < 0 || $offset > count($this->data)) {
            throw new InvalidArgumentException('Index out of bounds: ' . $offset . ' [0,' . count($this->data) . ']');
        }

        if (is_null($value)) {
            unset($this->data[$offset]);
            $res = new Tuple(array_values($this->data), $this->isUsingBracket()); // reindex
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

    public function slice(int $offset = 0, ?int $length = null): ISlice
    {
        return new Tuple(
            array_slice($this->data, $offset, $length),
            $this->isUsingBracket()
        );
    }

    public function cons($x): ICons
    {
        return new Tuple([$x, ...$this->data], $this->isUsingBracket());
    }

    public function equals($other): bool
    {
        return $this == $other;
    }

    public function cdr(): ?ICdr
    {
        if (count($this->data) - 1 > 0) {
            return new Tuple(array_slice($this->data, 1), $this->isUsingBracket());
        }

        return null;
    }

    public function rest(): IRest
    {
        return new Tuple(array_slice($this->data, 1), $this->isUsingBracket());
    }

    public function push($x): IPush
    {
        return new Tuple([...$this->data, $x]);
    }

    public function concat($xs): IConcat
    {
        $newData = $this->data;
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
}
