<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\HashSet;

/**
 * @template TValue
 *
 * @extends AbstractTransientSet<TValue>
 */
final readonly class TransientHashSet extends AbstractTransientSet
{
    public function __toString(): string
    {
        return '<TransientSet count=' . $this->transientMap->count() . '>';
    }

    /**
     * @return PersistentHashSetInterface<TValue>
     */
    public function persistent(): PersistentHashSetInterface
    {
        return new PersistentHashSet($this->hasher, null, $this->transientMap->persistent());
    }
}
