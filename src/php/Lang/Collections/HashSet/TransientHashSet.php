<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\HashSet;

/**
 * @template V
 *
 * @extends AbstractTransientSet<V>
 */
final readonly class TransientHashSet extends AbstractTransientSet
{
    public function __toString(): string
    {
        return '<TransientSet count=' . $this->transientMap->count() . '>';
    }

    /**
     * @return PersistentHashSetInterface<V>
     */
    public function persistent(): PersistentHashSetInterface
    {
        return new PersistentHashSet($this->hasher, null, $this->transientMap->persistent());
    }
}
