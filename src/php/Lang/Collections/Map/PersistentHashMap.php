<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Map;

use EmptyIterator;
use Phel\Lang\EqualizerInterface;
use Phel\Lang\HasherInterface;
use RuntimeException;
use stdClass;
use Traversable;

use function count;

/**
 * @template K
 * @template V
 *
 * @extends AbstractPersistentMap<K, V>
 */
final class PersistentHashMap extends AbstractPersistentMap
{
    private static ?stdClass $NOT_FOUND = null;

    /**
     * @param ?HashMapNodeInterface<K, V> $root
     * @param V $nullValue
     */
    public function __construct(
        HasherInterface $hasher,
        EqualizerInterface $equalizer,
        ?PersistentMapInterface $meta,
        private readonly int $count,
        private readonly ?HashMapNodeInterface $root,
        private readonly bool $hasNull,
        private $nullValue,
    ) {
        parent::__construct($hasher, $equalizer, $meta);
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

    public static function getNotFound(): stdClass
    {
        if (!self::$NOT_FOUND instanceof stdClass) {
            self::$NOT_FOUND = new stdClass();
        }

        return self::$NOT_FOUND;
    }

    public function withMeta(?PersistentMapInterface $meta): self
    {
        return new self($this->hasher, $this->equalizer, $meta, $this->count, $this->root, $this->hasNull, $this->nullValue);
    }

    public function contains($key): bool
    {
        if ($key === null) {
            return $this->hasNull;
        }

        if (!$this->root instanceof HashMapNodeInterface) {
            return false;
        }

        return $this->root->find(0, $this->hasher->hash($key), $key, self::getNotFound()) !== self::getNotFound();
    }

    public function put($key, $value): self
    {
        if ($key === null) {
            if ($this->hasNull && $this->equalizer->equals($value, $this->nullValue)) {
                return $this;
            }

            return new self($this->hasher, $this->equalizer, $this->meta, $this->hasNull ? $this->count : $this->count + 1, $this->root, true, $value);
        }

        $addedLeaf = new Box(false);
        $newRoot = $this->root ?? IndexedNode::empty($this->hasher, $this->equalizer);
        $newRoot = $newRoot->put(0, $this->hasher->hash($key), $key, $value, $addedLeaf);

        if ($newRoot === $this->root) {
            return $this;
        }

        return new self($this->hasher, $this->equalizer, $this->meta, $addedLeaf->getValue() === false ? $this->count : $this->count + 1, $newRoot, $this->hasNull, $this->nullValue);
    }

    public function remove($key): self
    {
        if ($key === null) {
            return $this->hasNull ? new self($this->hasher, $this->equalizer, $this->meta, $this->count - 1, $this->root, false, null) : $this;
        }

        if (!$this->root instanceof HashMapNodeInterface) {
            return $this;
        }

        $newRoot = $this->root->remove(0, $this->hasher->hash($key), $key);

        if ($newRoot == $this->root) {
            return $this;
        }

        return new self($this->hasher, $this->equalizer, $this->meta, $this->count - 1, $newRoot, $this->hasNull, $this->nullValue);
    }

    public function find($key)
    {
        if ($key === null) {
            if ($this->hasNull) {
                return $this->nullValue;
            }

            return null;
        }

        if (!$this->root instanceof HashMapNodeInterface) {
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
        if ($this->root instanceof HashMapNodeInterface) {
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
                $this->nullValue,
            ),
        );
    }
}
