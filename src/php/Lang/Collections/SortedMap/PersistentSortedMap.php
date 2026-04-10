<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\SortedMap;

use Closure;
use Phel\Lang\Collections\Map\AbstractPersistentMap;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Map\TransientMapWrapper;
use Phel\Lang\EqualizerInterface;
use Phel\Lang\HasherInterface;
use Phel\Lang\NamedInterface;
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
    /**
     * @param array<int, mixed>           $array      Flat [k, v, k, v, ...] array in sorted key order
     * @param ?Closure(mixed, mixed): int $comparator
     */
    public function __construct(
        HasherInterface $hasher,
        EqualizerInterface $equalizer,
        ?PersistentMapInterface $meta,
        private readonly array $array,
        private readonly ?Closure $comparator = null,
    ) {
        parent::__construct($hasher, $equalizer, $meta);
    }

    /**
     * @param ?callable(mixed, mixed): int $comparator
     */
    public static function empty(HasherInterface $hasher, EqualizerInterface $equalizer, ?callable $comparator = null): self
    {
        return new self($hasher, $equalizer, null, [], self::toClosure($comparator));
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
        return new self($this->hasher, $this->equalizer, $meta, $this->array, $this->comparator);
    }

    public function contains($key): bool
    {
        return $this->binarySearchIndex($key) >= 0;
    }

    public function put($key, $value): PersistentMapInterface
    {
        $idx = $this->binarySearchIndex($key);

        if ($idx >= 0) {
            if ($this->equalizer->equals($this->array[$idx + 1], $value)) {
                return $this;
            }

            $newArray = $this->array;
            $newArray[$idx + 1] = $value;

            return new self($this->hasher, $this->equalizer, $this->meta, $newArray, $this->comparator);
        }

        $insertAt = -($idx + 1);
        $newArray = $this->array;
        array_splice($newArray, $insertAt, 0, [$key, $value]);

        return new self($this->hasher, $this->equalizer, $this->meta, $newArray, $this->comparator);
    }

    public function remove($key): self
    {
        $idx = $this->binarySearchIndex($key);

        if ($idx < 0) {
            return $this;
        }

        $newArray = $this->array;
        array_splice($newArray, $idx, 2);

        return new self($this->hasher, $this->equalizer, $this->meta, $newArray, $this->comparator);
    }

    public function find($key)
    {
        $idx = $this->binarySearchIndex($key);
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
                $this->comparator,
            ),
        );
    }

    /**
     * @return ?callable(mixed, mixed): int
     */
    public function getComparator(): ?callable
    {
        return $this->comparator;
    }

    private static function toClosure(?callable $comparator): ?Closure
    {
        if ($comparator === null) {
            return null;
        }

        return $comparator instanceof Closure ? $comparator : Closure::fromCallable($comparator);
    }

    /**
     * Default comparator that handles NamedInterface objects (Keywords, Symbols)
     * by comparing their full names, and falls back to <=> for everything else.
     */
    private static function defaultCompare(mixed $a, mixed $b): int
    {
        if ($a instanceof NamedInterface && $b instanceof NamedInterface) {
            return $a->getFullName() <=> $b->getFullName();
        }

        return $a <=> $b;
    }

    /**
     * Binary search for a key in the sorted array.
     *
     * @return int The array index (even) if found, or -(insertionPoint) - 1 if not found
     */
    private function binarySearchIndex(mixed $key): int
    {
        /** @var Closure(mixed, mixed): int $comparator */
        $comparator = $this->comparator ?? static fn(mixed $a, mixed $b): int => self::defaultCompare($a, $b);
        $low = 0;
        $high = (int) (count($this->array) / 2) - 1;

        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);
            $cmp = $comparator($this->array[$mid * 2], $key);

            if ($cmp < 0) {
                $low = $mid + 1;
            } elseif ($cmp > 0) {
                $high = $mid - 1;
            } else {
                return $mid * 2;
            }
        }

        return -(($low * 2) + 1);
    }
}
