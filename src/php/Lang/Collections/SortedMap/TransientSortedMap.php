<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\SortedMap;

use Closure;
use Phel\Lang\Collections\Exceptions\MethodNotSupportedException;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Map\TransientMapInterface;
use Phel\Lang\EqualizerInterface;
use Phel\Lang\HasherInterface;

use function count;

/**
 * @template K
 * @template V
 *
 * @implements TransientMapInterface<K, V>
 */
final class TransientSortedMap implements TransientMapInterface
{
    private readonly Closure $effectiveComparator;

    /**
     * @param array<int, mixed>           $array          Flat [k, v, k, v, ...] in sorted key order
     * @param ?Closure(mixed, mixed): int $userComparator Original user comparator (null = natural order)
     */
    public function __construct(
        private readonly HasherInterface $hasher,
        private readonly EqualizerInterface $equalizer,
        private array $array,
        private readonly ?Closure $userComparator = null,
    ) {
        $this->effectiveComparator = SortedArrayHelper::resolveComparator($userComparator);
    }

    public function contains($key): bool
    {
        return SortedArrayHelper::binarySearch($this->array, $key, $this->effectiveComparator) >= 0;
    }

    public function put($key, $value): self
    {
        $idx = SortedArrayHelper::binarySearch($this->array, $key, $this->effectiveComparator);

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
        $idx = SortedArrayHelper::binarySearch($this->array, $key, $this->effectiveComparator);

        if ($idx < 0) {
            return $this;
        }

        array_splice($this->array, $idx, 2);

        return $this;
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
        return new PersistentSortedMap($this->hasher, $this->equalizer, null, $this->array, $this->userComparator);
    }
}
