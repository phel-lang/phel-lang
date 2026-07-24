<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\SortedSet;

use Phel\Lang\Collections\HashSet\AbstractTransientSet;
use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;

/**
 * @template TValue
 *
 * @extends AbstractTransientSet<TValue>
 */
final readonly class TransientSortedSet extends AbstractTransientSet
{
    public function __toString(): string
    {
        return '<TransientSortedSet count=' . $this->transientMap->count() . '>';
    }

    /**
     * @return PersistentHashSetInterface<TValue>
     */
    public function persistent(): PersistentHashSetInterface
    {
        return new PersistentSortedSet($this->hasher, null, $this->transientMap->persistent());
    }
}
