<?php

declare(strict_types=1);

namespace Phel\Lang;

use ArrayAccess;
use Countable;
use Iterator;
use Phel\Printer\Printer;
use RuntimeException;

class Table extends AbstractType implements ArrayAccess, Countable, Iterator, SeqInterface
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
            throw new RuntimeException('A even number of elements must be provided');
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

    public static function fromTuple(Tuple $tuple): self
    {
        $table = self::fromKVArray($tuple->toArray());
        $table->setStartLocation($tuple->getStartLocation());
        $table->setEndLocation($tuple->getEndLocation());

        return $table;
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

    public function cdr(): ?CdrInterface
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

    public function rest(): RestInterface
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

    public function hash(): int
    {
        return crc32(spl_object_hash($this));
    }

    public function equals($other): bool
    {
        // Should be the same type
        if (!($other instanceof Table)) {
            return false;
        }

        // Should have the same length
        if (count($this) !== count($other)) {
            return false;
        }

        // Try to compare directly
        // This is faster if it works but it will not catch all cases
        // For example `(= @[:a] @[:a])` will fail because the Keyword Objects are
        // not the same reference but.
        // We could change the comparison operator to `==`. This will make the above
        // example work but fail another example `(= @[1] @["1"])
        // It would be helpful if the Object-comparison RFC (https://wiki.php.net/rfc/object-comparison)
        // would have been accepted but it is not.
        if ($other->data === $this->data) {
            return true;
        }

        // If direct comparison is not working
        // we have to iterate over all elements and compare the keys and values.
        foreach ($this as $key => $value) {
            if ($key === null) {
                return false;
            }

            if (!isset($other[$key])) {
                return false;
            }

            if (!$this->areEquals($value, $other[$key])) {
                return false;
            }
        }

        return true;
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
    private function offsetHash($offset): int
    {
        if ($offset instanceof AbstractType) {
            return $offset->hash();
        }

        if (is_object($offset)) {
            return crc32(spl_object_hash($offset));
        }

        return crc32((string) $offset);
    }

    public function __toString(): string
    {
        return Printer::readable()->print($this);
    }
}
