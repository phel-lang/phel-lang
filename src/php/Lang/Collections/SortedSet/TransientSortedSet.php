<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\SortedSet;

use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;
use Phel\Lang\Collections\HashSet\TransientHashSetInterface;
use Phel\Lang\Collections\Map\TransientMapInterface;
use Phel\Lang\HasherInterface;
use Stringable;

/**
 * @template V
 *
 * @implements TransientHashSetInterface<V>
 */
final readonly class TransientSortedSet implements TransientHashSetInterface, Stringable
{
    public function __construct(
        private HasherInterface $hasher,
        private TransientMapInterface $transientMap,
    ) {}

    public function __toString(): string
    {
        return '<TransientSortedSet count=' . $this->transientMap->count() . '>';
    }

    public function count(): int
    {
        return $this->transientMap->count();
    }

    public function contains($key): bool
    {
        return $this->transientMap->contains($key);
    }

    /**
     * @param V $value
     */
    public function add($value): TransientHashSetInterface
    {
        $this->transientMap->put($value, $value);

        return $this;
    }

    /**
     * @param V $value
     */
    public function remove($value): TransientHashSetInterface
    {
        $this->transientMap->remove($value);

        return $this;
    }

    public function persistent(): PersistentHashSetInterface
    {
        return new PersistentSortedSet($this->hasher, null, $this->transientMap->persistent());
    }
}
