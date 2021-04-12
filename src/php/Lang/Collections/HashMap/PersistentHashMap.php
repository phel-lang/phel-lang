<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\HashMap;

use EmptyIterator;
use Phel\Lang\AbstractType;
use Phel\Lang\EqualizerInterface;
use Phel\Lang\HasherInterface;
use Phel\Lang\Table;
use RuntimeException;
use Traversable;

/**
 * @template K
 * @template V
 *
 * @implements PersistentHashMapInterface<K, V>
 * @extends AbstractType<PersistentHashMap<K, V>>
 */
class PersistentHashMap extends AbstractType implements PersistentHashMapInterface
{
    private EqualizerInterface $equalizer;
    private HasherInterface $hasher;
    private int $count;
    /** @var ?HashMapNodeInterface<K, V> */
    private ?HashMapNodeInterface $root;
    private bool $hasNull;
    /** @var V */
    private $nullValue;
    private int $hashCache = 0;
    private ?PersistentHashMapInterface $meta;

    /** @var \stdclass|null */
    private static $NOT_FOUND;

    /**
     * @param V $nullValue
     */
    public function __construct(HasherInterface $hasher, EqualizerInterface $equalizer, ?PersistentHashMapInterface $meta, int $count, ?HashMapNodeInterface $root, bool $hasNull, $nullValue)
    {
        $this->hasher = $hasher;
        $this->equalizer = $equalizer;
        $this->meta = $meta;
        $this->count = $count;
        $this->root = $root;
        $this->hasNull = $hasNull;
        $this->nullValue = $nullValue;
    }

    public static function empty(HasherInterface $hasher, EqualizerInterface $equalizer): self
    {
        return new self($hasher, $equalizer, null, 0, null, false, null);
    }

    public static function getNotFound(): \stdclass
    {
        if (!self::$NOT_FOUND) {
            self::$NOT_FOUND = new \stdclass();
        }

        return self::$NOT_FOUND;
    }

    public function getMeta(): ?PersistentHashMapInterface
    {
        return $this->meta;
    }

    public function withMeta(?PersistentHashMapInterface $meta)
    {
        return new PersistentHashMap($this->hasher, $this->equalizer, $meta, $this->count, $this->root, $this->hasNull, $this->nullValue);
    }

    public function containsKey($key): bool
    {
        if ($key === null) {
            return $this->hasNull;
        }

        if ($this->root === null) {
            return false;
        }

        return $this->root->find(0, $this->hasher->hash($key), $key, self::getNotFound()) !== self::getNotFound();
    }

    public function put($key, $value): PersistentHashMap
    {
        if ($key === null) {
            if ($this->hasNull && $this->equalizer->equals($value, $this->nullValue)) {
                return $this;
            }

            return new PersistentHashMap($this->hasher, $this->equalizer, $this->meta, $this->hasNull ? $this->count : $this->count + 1, $this->root, true, $value);
        }

        $addedLeaf = new Box(false);
        $newRoot = ($this->root === null) ? IndexedNode::empty($this->hasher, $this->equalizer) : $this->root;
        $newRoot = $newRoot->put(0, $this->hasher->hash($key), $key, $value, $addedLeaf);

        if ($newRoot === $this->root) {
            return $this;
        }

        return new PersistentHashMap($this->hasher, $this->equalizer, $this->meta, $addedLeaf->getValue() === false ? $this->count : $this->count +1, $newRoot, $this->hasNull, $this->nullValue);
    }

    public function remove($key): PersistentHashMap
    {
        if ($key === null) {
            return $this->hasNull ? new PersistentHashMap($this->hasher, $this->equalizer, $this->meta, $this->count - 1, $this->root, false, null) : $this;
        }

        if ($this->root === null) {
            return $this;
        }

        $newRoot = $this->root->remove(0, $this->hasher->hash($key), $key);

        if ($newRoot == $this->root) {
            return $this;
        }

        return new PersistentHashMap($this->hasher, $this->equalizer, $this->meta, $this->count - 1, $newRoot, $this->hasNull, $this->nullValue);
    }

    public function find($key)
    {
        if ($key === null) {
            if ($this->hasNull) {
                return $this->nullValue;
            }

            return null;
        }

        if ($this->root === null) {
            return null;
        }

        return $this->root->find(0, $this->hasher->hash($key), $key, null);
    }

    public function count(): int
    {
        return $this->count;
    }

    public function getIterator(): Traversable
    {
        if ($this->root) {
            return $this->root->getIterator();
        }

        return new EmptyIterator();
    }

    public function hash(): int
    {
        if ($this->hashCache === 0) {
            $this->hashCache = 1;
            foreach ($this as $key => $value) {
                $this->hashCache += $this->hasher->hash($key) ^ $this->hasher->hash($value);
            }
        }

        return $this->hashCache;
    }

    public function equals($other): bool
    {
        if (!$other instanceof PersistentHashMap) {
            return false;
        }

        if ($this->count !== $other->count()) {
            return false;
        }

        foreach ($this as $key => $value) {
            if (!$other->containsKey($key)) {
                return false;
            }

            if (!$this->equalizer->equals($value, $other->find($key))) {
                return false;
            }
        }

        return true;
    }

    public function toTable(): Table
    {
        $t = Table::empty();
        foreach ($this as $key => $value) {
            $t[$key] = $value;
        }
        return $t;
    }

    /**
     * @param K $offset
     *
     * @return V|null
     */
    public function offsetGet($offset)
    {
        return $this->find($offset);
    }

    /**
     * @param K $offset
     */
    public function offsetExists($offset): bool
    {
        return $this->containsKey($offset);
    }

    public function offsetSet($offset, $value): void
    {
        throw new RuntimeException('Method offsetSet is not supported on PersistentHashMap');
    }

    public function offsetUnset($offset): void
    {
        throw new RuntimeException('Method offsetUnset is not supported on PersistentHashMap');
    }

    public function merge(PersistentHashMapInterface $other): PersistentHashMapInterface
    {
        $m = $this;
        foreach ($other as $k => $v) {
            $m = $m->put($k, $v);
        }

        return $m;
    }
}
