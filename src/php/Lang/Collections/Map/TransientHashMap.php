<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Map;

use Phel\Lang\Collections\Exceptions\MethodNotSupportedException;
use Phel\Lang\EqualizerInterface;
use Phel\Lang\HasherInterface;

/**
 * @template K
 * @template V
 *
 * @implements TransientMapInterface<K, V>
 */
class TransientHashMap implements TransientMapInterface
{
    private EqualizerInterface $equalizer;
    private HasherInterface $hasher;
    private int $count;
    /** @var ?HashMapNodeInterface<K, V> */
    private ?HashMapNodeInterface $root;
    private bool $hasNull;
    /** @var ?V */
    private $nullValue;

    /** @var \stdclass|null */
    private static $NOT_FOUND;

    /**
     * @param V $nullValue
     */
    public function __construct(HasherInterface $hasher, EqualizerInterface $equalizer, int $count, ?HashMapNodeInterface $root, bool $hasNull, $nullValue)
    {
        $this->hasher = $hasher;
        $this->equalizer = $equalizer;
        $this->count = $count;
        $this->root = $root;
        $this->hasNull = $hasNull;
        $this->nullValue = $nullValue;
    }

    public static function empty(HasherInterface $hasher, EqualizerInterface $equalizer): TransientHashMap
    {
        return new self($hasher, $equalizer, 0, null, false, null);
    }

    public static function getNotFound(): \stdclass
    {
        if (!self::$NOT_FOUND) {
            self::$NOT_FOUND = new \stdclass();
        }

        return self::$NOT_FOUND;
    }

    public function contains($key): bool
    {
        if ($key === null) {
            return $this->hasNull;
        }

        if ($this->root === null) {
            return false;
        }

        return $this->root->find(0, $this->hasher->hash($key), $key, self::getNotFound()) !== self::getNotFound();
    }

    public function put($key, $value): self
    {
        if ($key === null) {
            if (!$this->equalizer->equals($value, $this->nullValue)) {
                $this->nullValue = $value;
            }

            if (!$this->hasNull) {
                $this->count++;
                $this->hasNull = true;
            }

            return $this;
        }

        $addedLeaf = new Box(false);
        $newRoot = ($this->root === null) ? IndexedNode::empty($this->hasher, $this->equalizer) : $this->root;
        $newRoot = $newRoot->put(0, $this->hasher->hash($key), $key, $value, $addedLeaf);

        if ($newRoot !== $this->root) {
            $this->root = $newRoot;
        }

        if ($addedLeaf->getValue() === true) {
            $this->count++;
        }

        return $this;
    }

    public function remove($key): self
    {
        if ($key === null) {
            if (!$this->hasNull) {
                return $this;
            }

            $this->hasNull = false;
            $this->nullValue = null;
            $this->count--;

            return $this;
        }

        if ($this->root === null) {
            return $this;
        }

        $newRoot = $this->root->remove(0, $this->hasher->hash($key), $key);

        if ($newRoot != $this->root) {
            $this->root = $newRoot;
            $this->count--;
        }

        return $this;
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

    public function persistent(): PersistentMapInterface
    {
        return new PersistentHashMap($this->hasher, $this->equalizer, null, $this->count, $this->root, $this->hasNull, $this->nullValue);
    }

    /**
     * @param K $offset
     *
     * @return V|null
     */
    public function offsetGet($offset): mixed
    {
        return $this->find($offset);
    }

    /**
     * @param K $offset
     */
    public function offsetExists($offset): bool
    {
        return $this->contains($offset);
    }

    public function offsetSet($offset, $value): void
    {
        throw new MethodNotSupportedException('Method offsetSet is not supported on TransientMap');
    }

    public function offsetUnset($offset): void
    {
        throw new MethodNotSupportedException('Method offsetUnset is not supported on TransientMap');
    }
}
