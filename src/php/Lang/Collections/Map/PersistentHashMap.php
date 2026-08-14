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
use function sprintf;

/**
 * @template TKey
 * @template TValue
 *
 * @extends AbstractPersistentMap<TKey, TValue>
 */
final class PersistentHashMap extends AbstractPersistentMap
{
    /** @use TransientMergeStrategyTrait<TKey, TValue> */
    use TransientMergeStrategyTrait;

    private static ?stdClass $NOT_FOUND = null;

    /**
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     * @param HashMapNodeInterface<TKey, TValue>|null   $root
     * @param mixed                                     $nullValue
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

    /**
     * @return self<TKey, TValue>
     */
    public static function empty(HasherInterface $hasher, EqualizerInterface $equalizer): self
    {
        /** @var self<TKey, TValue> $result */
        $result = new self($hasher, $equalizer, null, 0, null, false, null);

        return $result;
    }

    /**
     * @param array<int, mixed> $kvs
     *
     * @return PersistentMapInterface<TKey, TValue>
     */
    public static function fromArray(HasherInterface $hasher, EqualizerInterface $equalizer, array $kvs): PersistentMapInterface
    {
        if ($kvs === []) {
            return self::empty($hasher, $equalizer);
        }

        if (count($kvs) % 2 !== 0) {
            throw new RuntimeException(sprintf(
                'An even number of elements must be provided to build a map, got %d',
                count($kvs),
            ));
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

    /**
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     */
    public function withMeta(?PersistentMapInterface $meta): static
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

    /**
     * @param TKey   $key
     * @param TValue $value
     *
     * @return self<TKey, TValue>
     */
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

    /**
     * @param TKey $key
     *
     * @return self<TKey, TValue>
     */
    public function remove($key): self
    {
        if ($key === null) {
            return $this->hasNull ? new self($this->hasher, $this->equalizer, $this->meta, $this->count - 1, $this->root, false, null) : $this;
        }

        if (!$this->root instanceof HashMapNodeInterface) {
            return $this;
        }

        $newRoot = $this->root->remove(0, $this->hasher->hash($key), $key);

        if ($newRoot === $this->root) {
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
        return max(0, $this->count);
    }

    /**
     * `TKey|null` because `nil` is a legitimate key here and is the one this
     * iterator used to skip; see below.
     *
     * @return Traversable<TKey|null, TValue>
     */
    public function getIterator(): Traversable
    {
        // The common case returns the trie's own iterator untouched. Wrapping
        // every map in a generator to prepend a `nil` entry that is almost
        // never there cost 38% on iterating a small map, which the benchmark
        // gate caught.
        if (!$this->hasNull) {
            if ($this->root instanceof HashMapNodeInterface) {
                return $this->root->getIterator();
            }

            return new EmptyIterator();
        }

        return $this->iterateWithNullKey();
    }

    /**
     * @return TransientMapWrapper<TKey, TValue>
     */
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
                $this->meta,
            ),
        );
    }

    /**
     * The `nil` key is not stored in the trie: `put()` keeps it in
     * `$hasNull`/`$nullValue` because `null` has no hash path. It has to be
     * yielded too, or the map iterates one entry short of its own `count()`
     * and everything built on iteration silently drops it: `keys`, `vals`,
     * `for ... :pairs`, and `into`, which loses the entry while copying.
     *
     * @return Traversable<TKey|null, TValue>
     */
    private function iterateWithNullKey(): Traversable
    {
        yield null => $this->nullValue;

        if ($this->root instanceof HashMapNodeInterface) {
            yield from $this->root->getIterator();
        }
    }
}
