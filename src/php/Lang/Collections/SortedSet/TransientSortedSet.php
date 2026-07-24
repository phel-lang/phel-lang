<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\SortedSet;

use Phel\Lang\Collections\HashSet\AbstractTransientSet;
use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;

/**
 * @template V
 *
 * @extends AbstractTransientSet<V>
 */
final readonly class TransientSortedSet extends AbstractTransientSet
{
    public function __toString(): string
    {
        return '<TransientSortedSet count=' . $this->transientMap->count() . '>';
    }

    /**
     * @return PersistentHashSetInterface<V>
     */
    public function persistent(): PersistentHashSetInterface
    {
        return new PersistentSortedSet($this->hasher, null, $this->transientMap->persistent());
    }
}
