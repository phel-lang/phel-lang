<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Map;

use EmptyIterator;
use Phel\Lang\EqualizerInterface;
use Phel\Lang\HasherInterface;
use RuntimeException;
use Traversable;

/**
 * @template K
 * @template V
 *
 * @extends AbstractPersistentMap<K, V>
 */
class PersistentHashMap extends AbstractPersistentMap
{
    private int $count;
    /** @var ?HashMapNodeInterface<K, V> */
    private ?HashMapNodeInterface $root;
    private bool $hasNull;
    /** @var V */
    private $nullValue;

    /** @var \stdclass|null */
    private static $NOT_FOUND;

    /**
     * @param V $nullValue
     */
    public function __construct(HasherInterface $hasher, EqualizerInterface $equalizer, ?PersistentMapInterface $meta, int $count, ?HashMapNodeInterface $root, bool $hasNull, $nullValue)
    {
        parent::__construct($hasher, $equalizer, $meta);
        $this->count = $count;
        $this->root = $root;
        $this->hasNull = $hasNull;
        $this->nullValue = $nullValue;
    }

    public static function empty(HasherInterface $hasher, EqualizerInterface $equalizer): self
    {
        return new self($hasher, $equalizer, null, 0, null, false, null);
    }

    public static function fromArray(HasherInterface $hasher, EqualizerInterface $equalizer, array $kvs): PersistentMapInterface
    {
        if (count($kvs) % 2 !== 0) {
            throw new RuntimeException('A even number of elements must be provided');
        }

        $result = self::empty($hasher, $equalizer)->asTransient();
        for ($i = 0, $l = count($kvs); $i < $l; $i += 2) {
            $result->put($kvs[$i], $kvs[$i + 1]);
        }
        return $result->persistent();
    }

    public static function getNotFound(): \stdclass
    {
        if (!self::$NOT_FOUND) {
            self::$NOT_FOUND = new \stdclass();
        }

        return self::$NOT_FOUND;
    }

    public function withMeta(?PersistentMapInterface $meta)
    {
        return new PersistentHashMap($this->hasher, $this->equalizer, $meta, $this->count, $this->root, $this->hasNull, $this->nullValue);
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

        return new PersistentHashMap($this->hasher, $this->equalizer, $this->meta, $addedLeaf->getValue() === false ? $this->count : $this->count + 1, $newRoot, $this->hasNull, $this->nullValue);
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

    public function asTransient(): TransientMapWrapper
    {
        return new TransientMapWrapper(
            new TransientHashMap(
                $this->hasher,
                $this->equalizer,
                $this->count,
                $this->root,
                $this->hasNull,
                $this->nullValue
            )
        );
    }
}
