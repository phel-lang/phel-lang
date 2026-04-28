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
     */
    public static function empty(HasherInterface $hasher, EqualizerInterface $equalizer, ?callable $comparator = null): self
    {
        $closure = $comparator !== null ? SortedArrayHelper::resolveComparator($comparator) : null;

        return new self($hasher, $equalizer, null, [], $closure);
    }

    /**
     * @param array<int, mixed>            $kvs
     * @param ?callable(mixed, mixed): int $comparator
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

        /** @var self */
        return $result->persistent();
    }

    public function withMeta(?PersistentMapInterface $meta): static
    {
        return new self($this->hasher, $this->equalizer, $meta, $this->array, $this->userComparator);
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

            return new self($this->hasher, $this->equalizer, $this->meta, $newArray, $this->userComparator);
        }

        $insertAt = -($idx + 1);
        $newArray = $this->array;
        array_splice($newArray, $insertAt, 0, [$key, $value]);

        return new self($this->hasher, $this->equalizer, $this->meta, $newArray, $this->userComparator);
    }

    public function remove($key): self
    {
        $idx = SortedArrayHelper::binarySearch($this->array, $key, $this->effectiveComparator);

        if ($idx < 0) {
            return $this;
        }

        $newArray = $this->array;
        array_splice($newArray, $idx, 2);

        return new self($this->hasher, $this->equalizer, $this->meta, $newArray, $this->userComparator);
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
        return (int) (count($this->array) / 2);
    }

    public function getIterator(): Traversable
    {
        for ($i = 0, $cnt = count($this->array); $i < $cnt; $i += 2) {
            yield $this->array[$i] => $this->array[$i + 1];
        }
    }

    public function asTransient(): TransientMapWrapper
    {
        return new TransientMapWrapper(
            new TransientSortedMap(
                $this->hasher,
                $this->equalizer,
                $this->array,
                $this->userComparator,
            ),
        );
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
}
