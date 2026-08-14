<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Map;

use Phel\Lang\EqualizerInterface;
use Phel\Lang\HasherInterface;
use RuntimeException;
use Traversable;

use function count;
use function sprintf;

/**
 * Map implementation based on a single array. The array stores the key value pair directly.
 *
 * This implementation is only appropriate for very small maps, since the array is copied
 * every time the map changes.
 *
 * @template TKey
 * @template TValue
 *
 * @extends AbstractPersistentMap<TKey, TValue>
 */
final class PersistentArrayMap extends AbstractPersistentMap
{
    /** @use TransientMergeStrategyTrait<TKey, TValue> */
    use TransientMergeStrategyTrait;

    /**
     * The entry count at which a map stops being a flat array and becomes a
     * hash map.
     *
     * An array map finds a key by scanning its entries and calling
     * `equalsKey()` on each, so a read is O(n) where the hash map it promotes
     * to is O(1). At the old value of 16 that made a 16-entry map **4.9x
     * slower to read than a 20-entry one** (4.71us against 0.96us), which is
     * backwards: crossing the threshold made reads faster, not slower.
     *
     * 8 because it is free. Building an 8-entry map is unchanged (34.0us
     * against 34.1us) and building a 16-entry one is unchanged (70.3us against
     * 69.9us), while reading the last key of a 12-entry map goes 3.53us to
     * 1.00us and of a 16-entry map 4.71us to 0.80us.
     *
     * Do not lower this further without reading #3172. A value of 4 makes a
     * three-entry map *literal* containing `nil`, `false` and `0` keys lose
     * one of them, somewhere above the collection layer (`fromArray` and the
     * transient both handle those keys correctly at 4 when called directly).
     * That is unexplained, so 8 is as far as this goes.
     *
     * Iteration order is what an array map buys, and is why this is a
     * threshold rather than a deletion: a map below it iterates in insertion
     * order. Nothing documents that as a guarantee, `sequential?` says maps
     * are not sequential, and no test here, in the Phel suite or in the
     * Clojure suite depends on it between 9 and 16 entries.
     */
    public const int MAX_SIZE = 8;

    /**
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     * @param array<int, mixed>                         $array
     */
    public function __construct(
        HasherInterface $hasher,
        EqualizerInterface $equalizer,
        ?PersistentMapInterface $meta,
        private array $array,
    ) {
        parent::__construct($hasher, $equalizer, $meta);
    }

    /**
     * @return self<TKey, TValue>
     */
    public static function empty(HasherInterface $hasher, EqualizerInterface $equalizer): self
    {
        /** @var self<TKey, TValue> $result */
        $result = new self($hasher, $equalizer, null, []);

        return $result;
    }

    /**
     * @param array<int, mixed> $kvs
     *
     * @return PersistentMapInterface<TKey, TValue>
     */
    public static function fromArray(HasherInterface $hasher, EqualizerInterface $equalizer, array $kvs): PersistentMapInterface
    {
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

    /**
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     */
    public function withMeta(?PersistentMapInterface $meta): static
    {
        /** @var static $result */
        $result = new self($this->hasher, $this->equalizer, $meta, $this->array);

        return $result;
    }

    public function contains($key): bool
    {
        return $this->findIndex($key) !== false;
    }

    public function put($key, $value): PersistentMapInterface
    {
        $index = $this->findIndex($key);

        if ($index !== false && $this->equalizer->equals($this->array[$index + 1], $value)) {
            return $this;
        }

        if ($index === false && $this->count() >= self::MAX_SIZE) {
            /** @var PersistentMapInterface<TKey, TValue> $promoted */
            $promoted = PersistentHashMap::fromArray($this->hasher, $this->equalizer, $this->array)->put($key, $value);

            return $promoted;
        }

        $newArray = $this->array;
        if ($index === false) {
            $newArray[] = $key;
            $newArray[] = $value;
        } else {
            $newArray[$index + 1] = $value;
        }

        /** @var self<TKey, TValue> $result */
        $result = new self($this->hasher, $this->equalizer, $this->meta, $newArray);

        return $result;
    }

    /**
     * @param TKey $key
     *
     * @return self<TKey, TValue>
     */
    public function remove($key): self
    {
        $index = $this->findIndex($key);

        if ($index === false) {
            return $this;
        }

        $newArray = $this->array;
        array_splice($newArray, $index, 2);

        /** @var self<TKey, TValue> $result */
        $result = new self($this->hasher, $this->equalizer, $this->meta, $newArray);

        return $result;
    }

    public function find($key)
    {
        $index = $this->findIndex($key);
        if ($index === false) {
            return null;
        }

        return $this->array[$index + 1];
    }

    public function count(): int
    {
        return max(0, intdiv(count($this->array), 2));
    }

    /**
     * @return Traversable<TKey, TValue>
     */
    public function getIterator(): Traversable
    {
        for ($i = 0, $cnt = count($this->array); $i < $cnt; $i += 2) {
            yield $this->array[$i] => $this->array[$i + 1];
        }
    }

    /**
     * @return TransientMapWrapper<TKey, TValue>
     */
    public function asTransient(): TransientMapWrapper
    {
        /** @var TransientMapWrapper<TKey, TValue> $result */
        $result = new TransientMapWrapper(
            new TransientArrayMap(
                $this->hasher,
                $this->equalizer,
                $this->array,
                $this->meta,
            ),
        );

        return $result;
    }

    /**
     * @param TKey $key
     */
    private function findIndex(mixed $key): int|false
    {
        for ($i = 0, $cnt = count($this->array); $i < $cnt; $i += 2) {
            $k = $this->array[$i];
            if ($this->equalizer->equalsKey($k, $key)) {
                return $i;
            }
        }

        return false;
    }
}
