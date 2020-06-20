<?php

declare(strict_types=1);

namespace Phel\Lang;

use ArrayAccess;
use Countable;
use Exception;
use Iterator;
use Phel\Printer;

class Table extends Phel implements ArrayAccess, Countable, Iterator
{
    protected array $data = [];

    protected array $keys = [];

    public static function empty(): Table
    {
        return new self();
    }

    /**
     * Create a Table from a list of key-value pairs.
     *
     * @param mixed[] $kvs The key-value pairs.
     *
     * @return Table
     */
    public static function fromKVs(...$kvs): Table
    {
        if (count($kvs) % 2 !== 0) {
            // TODO: Better exception
            throw new Exception('A even number of elements must be provided');
        }

        $result = new Table();
        for ($i = 0, $l = count($kvs); $i < $l; $i += 2) {
            $result[$kvs[$i]] = $kvs[$i+1];
        }
        return $result;
    }

    public static function fromKVArray(array $kvs): Table
    {
        return self::fromKVs(...$kvs);
    }

    public function offsetSet($offset, $value): void
    {
        $hash = $this->offsetHash($offset);

        $this->keys[$hash] = $offset;
        $this->data[$hash] = $value;
    }

    public function offsetExists($offset): bool
    {
        $hash = $this->offsetHash($offset);

        return isset($this->data[$hash]);
    }

    public function offsetUnset($offset): void
    {
        $hash = $this->offsetHash($offset);

        unset($this->keys[$hash], $this->data[$hash]);
    }

    /**
     * @return mixed|null
     */
    public function offsetGet($offset)
    {
        $hash = $this->offsetHash($offset);

        return $this->data[$hash] ?? null;
    }

    public function count(): int
    {
        return count($this->data);
    }

    public function current()
    {
        return current($this->data);
    }

    /**
     * @return mixed|null
     */
    public function key()
    {
        $key = key($this->data);

        if ($key !== null) {
            return $this->keys[$key];
        }

        return null;
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

    public function hash(): string
    {
        return spl_object_hash($this);
    }

    public function equals($other): bool
    {
        return $this == $other;
    }

    /**
     * Creates a hash for the given key.
     *
     * @param mixed $offset The access key of the Table.
     *
     * @return string
     */
    private function offsetHash($offset): string
    {
        if ($offset instanceof Phel) {
            return $offset->hash();
        }

        if (is_object($offset)) {
            return spl_object_hash($offset);
        }

        return (string) $offset;
    }

    public function __toString(): string
    {
        return Printer::readable()->print($this);
    }
}
