<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\SortedMap;

use Closure;
use Phel\Lang\Collections\Map\AbstractPersistentMap;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Map\TransientMapWrapper;
use Phel\Lang\EqualizerInterface;
use Phel\Lang\HasherInterface;
use RuntimeException;
use Traversable;

use function count;

/**
 * Sorted map implementation based on a flat array maintained in sorted key order.
 * Uses binary search for O(log n) lookups and O(n) inserts/removes.
 *
 * @template K
 * @template V
 *
 * @extends AbstractPersistentMap<K, V>
 */
final class PersistentSortedMap extends AbstractPersistentMap
{
    private readonly Closure $effectiveComparator;

    /**
     * @param array<int, mixed>           $array          Flat [k, v, k, v, ...] in sorted key order
     * @param ?Closure(mixed, mixed): int $userComparator Original user comparator (null = natural order)
     */
    public function __construct(
        HasherInterface $hasher,
        EqualizerInterface $equalizer,
        ?PersistentMapInterface $meta,
        private readonly array $array,
        private readonly ?Closure $userComparator = null,
    ) {
        parent::__construct($hasher, $equalizer, $meta);
        $this->effectiveComparator = SortedArrayHelper::adaptForBinarySearch(
            SortedArrayHelper::resolveComparator($userComparator),
        );
    }

    /**
     * @param ?callable(mixed, mixed): int $comparator
     *
     * @return self<K, V>
     */
    public static function empty(HasherInterface $hasher, EqualizerInterface $equalizer, ?callable $comparator = null): self
    {
        $closure = $comparator !== null ? SortedArrayHelper::resolveComparator($comparator) : null;

        /** @var self<K, V> $result */
        $result = new self($hasher, $equalizer, null, [], $closure);

        return $result;
    }

    /**
     * @param array<int, mixed>            $kvs
     * @param ?callable(mixed, mixed): int $comparator
     *
     * @return self<K, V>
     */
    public static function fromArray(HasherInterface $hasher, EqualizerInterface $equalizer, array $kvs, ?callable $comparator = null): self
    {
        if (count($kvs) % 2 !== 0) {
            throw new RuntimeException('A even number of elements must be provided');
        }

        $result = self::empty($hasher, $equalizer, $comparator)->asTransient();
        for ($i = 0, $l = count($kvs); $i < $l; $i += 2) {
            $result->put($kvs[$i], $kvs[$i + 1]);
        }

        /** @var self<K, V> */
        return $result->persistent();
    }

    /**
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     */
    public function withMeta(?PersistentMapInterface $meta): static
    {
        /** @var static $result */
        $result = new self($this->hasher, $this->equalizer, $meta, $this->array, $this->userComparator);

        return $result;
    }

    public function contains($key): bool
    {
        return SortedArrayHelper::binarySearch($this->array, $key, $this->effectiveComparator) >= 0;
    }

    public function put($key, $value): PersistentMapInterface
    {
        $idx = SortedArrayHelper::binarySearch($this->array, $key, $this->effectiveComparator);

        if ($idx >= 0) {
            if ($this->equalizer->equals($this->array[$idx + 1], $value)) {
                return $this;
            }

            $newArray = $this->array;
            $newArray[$idx + 1] = $value;

            /** @var self<K, V> $updated */
            $updated = new self($this->hasher, $this->equalizer, $this->meta, $newArray, $this->userComparator);

            return $updated;
        }

        $insertAt = -($idx + 1);
        $newArray = $this->array;
        array_splice($newArray, $insertAt, 0, [$key, $value]);

        /** @var self<K, V> $result */
        $result = new self($this->hasher, $this->equalizer, $this->meta, $newArray, $this->userComparator);

        return $result;
    }

    /**
     * @param mixed $key
     *
     * @return self<K, V>
     */
    public function remove($key): self
    {
        $idx = SortedArrayHelper::binarySearch($this->array, $key, $this->effectiveComparator);

        if ($idx < 0) {
            return $this;
        }

        $newArray = $this->array;
        array_splice($newArray, $idx, 2);

        /** @var self<K, V> $result */
        $result = new self($this->hasher, $this->equalizer, $this->meta, $newArray, $this->userComparator);

        return $result;
    }

    public function find($key)
    {
        $idx = SortedArrayHelper::binarySearch($this->array, $key, $this->effectiveComparator);
        if ($idx < 0) {
            return null;
        }

        return $this->array[$idx + 1];
    }

    public function count(): int
    {
        return max(0, intdiv(count($this->array), 2));
    }

    public function getIterator(): Traversable
    {
        for ($i = 0, $cnt = count($this->array); $i < $cnt; $i += 2) {
            yield $this->array[$i] => $this->array[$i + 1];
        }
    }

    /**
     * @return TransientMapWrapper<K, V>
     */
    public function asTransient(): TransientMapWrapper
    {
        /** @var TransientMapWrapper<K, V> $result */
        $result = new TransientMapWrapper(
            new TransientSortedMap(
                $this->hasher,
                $this->equalizer,
                $this->array,
                $this->userComparator,
            ),
        );

        return $result;
    }

    /**
     * Returns the user-provided comparator, or null for natural order.
     *
     * @return ?Closure(mixed, mixed): int
     */
    public function getComparator(): ?Closure
    {
        return $this->userComparator;
    }

    /**
     * Returns the comparator actually used for ordering: the user comparator
     * adapted to always return an int, or the natural-order default.
     *
     * @return Closure(mixed, mixed): int
     */
    public function getEffectiveComparator(): Closure
    {
        return $this->effectiveComparator;
    }
}
