<?php

declare(strict_types=1);

namespace Phel\Lang;

use ArrayAccess;
use Countable;
use Exception;
use Iterator;
use Phel\Printer\Printer;

class Table extends AbstractType implements ArrayAccess, Countable, Iterator, ISeq
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
     * @param mixed[] $kvs The key-value pairs
     */
    public static function fromKVs(...$kvs): Table
    {
        if (count($kvs) % 2 !== 0) {
            // TODO: Better exception
            throw new Exception('A even number of elements must be provided');
        }

        $result = new self();
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
     * @param mixed $offset
     *
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

    public function first()
    {
        $this->rewind();
        $key = $this->key();
        $value = $this->current();

        return new Tuple([$key, $value], true);
    }

    public function cdr(): ?ICdr
    {
        if ($this->count() <= 1) {
            return null;
        }

        $this->rewind();
        $this->next();

        $res = [];
        while ($this->valid()) {
            $key = $this->key();
            $value = $this->current();
            $res[] = new Tuple([$key, $value], true);

            $this->next();
        }

        $this->rewind();

        return new PhelArray($res);
    }

    public function rest(): IRest
    {
        $this->rewind();
        $this->next();

        $res = [];
        while ($this->valid()) {
            $key = $this->key();
            $value = $this->current();
            $res[] = new Tuple([$key, $value], true);

            $this->next();
        }

        $this->rewind();

        return new PhelArray($res);
    }

    public function hash(): string
    {
        return spl_object_hash($this);
    }

    public function equals($other): bool
    {
        return $this == $other;
    }

    public function toKeyValueList(): array
    {
        $result = [];
        foreach ($this as $key => $value) {
            $result[] = $key;
            $result[] = $value;
        }

        return $result;
    }

    /**
     * Creates a hash for the given key.
     *
     * @param mixed $offset The access key of the Table
     */
    private function offsetHash($offset): string
    {
        if ($offset instanceof AbstractType) {
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
