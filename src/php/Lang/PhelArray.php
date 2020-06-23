<?php

declare(strict_types=1);

namespace Phel\Lang;

use ArrayAccess;
use Countable;
use InvalidArgumentException;
use Iterator;

final class PhelArray extends AbstractType implements ArrayAccess, Countable, Iterator, ICons, ISlice, ICdr, IRest, IPop, IRemove, IPush, IConcat
{
    use HashableTrait;
    use PrintableTrait;
    use CountableTrait;
    use IterableTrait;

    private array $data;

    /**
     * @param mixed[] $data A list of all values
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function create(...$values): PhelArray
    {
        return new PhelArray($values);
    }

    public function offsetSet($offset, $value)
    {
        if ($offset < 0) {
            throw new InvalidArgumentException('Offset must be bigger or equal zero. Given: ' . $offset);
        }

        if ($offset < count($this->data)) {
            $this->data[$offset] = $value;
        } else {
            for ($i = count($this->data); $i < $offset; $i++) {
                $this->data[$i] = null;
            }

            $this->data[$offset] = $value;
        }
    }

    public function offsetExists($offset)
    {
        return isset($this->data[$offset]);
    }

    public function offsetUnset($offset)
    {
        if ($offset < 0 || $offset >= count($this->data)) {
            throw new InvalidArgumentException('Index out of bounds: ' . $offset . ' [0,' . count($this->data) . ')');
        }

        unset($this->data[$offset]);
        $this->data = array_values($this->data); // reindex
    }

    /**
     * @return mixed|null
     */
    public function offsetGet($offset)
    {
        return $this->data[$offset] ?? null;
    }

    public function equals($other): bool
    {
        return $this == $other;
    }

    public function slice(int $offset = 0, ?int $length = null): ISlice
    {
        return new PhelArray(array_slice($this->data, $offset, $length));
    }

    public function cons($x): ICons
    {
        array_unshift($this->data, $x);
        return $this;
    }

    public function toPhpArray(): array
    {
        return $this->data;
    }

    public function cdr(): ?ICdr
    {
        if (count($this->data) - 1 > 0) {
            return new PhelArray(array_slice($this->data, 1));
        }

        return null;
    }

    public function rest(): IRest
    {
        return new PhelArray(array_slice($this->data, 1));
    }

    public function pop()
    {
        return array_pop($this->data);
    }

    public function remove(int $offset, ?int $length = null): IRemove
    {
        $length = $length ?? count($this);

        return new PhelArray(array_splice($this->data, $offset, $length));
    }

    public function push($x): IPush
    {
        $this->data[] = $x;
        return $this;
    }

    public function concat($xs): IConcat
    {
        foreach ($xs as $x) {
            $this->data[] = $x;
        }
        return $this;
    }
}
