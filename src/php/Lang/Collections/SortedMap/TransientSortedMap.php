<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\SortedMap;

use Closure;
use Phel\Lang\Collections\Exceptions\MethodNotSupportedException;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Map\TransientMapInterface;
use Phel\Lang\EqualizerInterface;
use Phel\Lang\HasherInterface;
use Phel\Lang\NamedInterface;

use function count;

/**
 * @template K
 * @template V
 *
 * @implements TransientMapInterface<K, V>
 */
final class TransientSortedMap implements TransientMapInterface
{
    /**
     * @param array<int, mixed>           $array      Flat [k, v, k, v, ...] array in sorted key order
     * @param ?Closure(mixed, mixed): int $comparator
     */
    public function __construct(
        private readonly HasherInterface $hasher,
        private readonly EqualizerInterface $equalizer,
        private array $array,
        private readonly ?Closure $comparator = null,
    ) {}

    public function contains($key): bool
    {
        return $this->binarySearchIndex($key) >= 0;
    }

    public function put($key, $value): self
    {
        $idx = $this->binarySearchIndex($key);

        if ($idx >= 0) {
            if ($this->equalizer->equals($this->array[$idx + 1], $value)) {
                return $this;
            }

            $this->array[$idx + 1] = $value;

            return $this;
        }

        $insertAt = -($idx + 1);
        array_splice($this->array, $insertAt, 0, [$key, $value]);

        return $this;
    }

    public function remove($key): self
    {
        $idx = $this->binarySearchIndex($key);

        if ($idx < 0) {
            return $this;
        }

        array_splice($this->array, $idx, 2);

        return $this;
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

    /**
     * @param K $offset
     *
     * @return V|null
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->find($offset);
    }

    /**
     * @param K $offset
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->contains($offset);
    }

    public function offsetSet($offset, $value): void
    {
        throw new MethodNotSupportedException('Method offsetSet is not supported on TransientSortedMap');
    }

    public function offsetUnset($offset): void
    {
        throw new MethodNotSupportedException('Method offsetUnset is not supported on TransientSortedMap');
    }

    public function persistent(): PersistentMapInterface
    {
        return new PersistentSortedMap($this->hasher, $this->equalizer, null, $this->array, $this->comparator);
    }

    /**
     * @return int The array index (even) if found, or -(insertionPoint) - 1 if not found
     */
    private static function defaultCompare(mixed $a, mixed $b): int
    {
        if ($a instanceof NamedInterface && $b instanceof NamedInterface) {
            return $a->getFullName() <=> $b->getFullName();
        }

        return $a <=> $b;
    }

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
