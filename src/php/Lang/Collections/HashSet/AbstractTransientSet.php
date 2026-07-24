<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\HashSet;

use Phel\Lang\Collections\Map\TransientMapInterface;
use Phel\Lang\HasherInterface;
use Stringable;

/**
 * Shared behaviour of the transient set flavours: both are a thin facade over a
 * transient map that stores each member as its own key. Subclasses decide only
 * how they print and which persistent set they freeze into; the ordering lives
 * entirely in the backing map.
 *
 * @template TValue
 *
 * @implements TransientHashSetInterface<TValue>
 */
abstract readonly class AbstractTransientSet implements TransientHashSetInterface, Stringable
{
    /**
     * @param TransientMapInterface<TValue, TValue> $transientMap
     */
    public function __construct(
        protected HasherInterface $hasher,
        protected TransientMapInterface $transientMap,
    ) {}

    abstract public function __toString(): string;

    /**
     * Membership lookup so transient sets remain callable like their persistent
     * counterparts: `((transient #{:a}) :a) ; => :a`, else `nil`.
     *
     * @param TValue $key
     *
     * @return TValue|null
     */
    public function __invoke(mixed $key): mixed
    {
        return $this->transientMap->find($key);
    }

    public function count(): int
    {
        return $this->transientMap->count();
    }

    /**
     * @param mixed $key
     */
    public function contains($key): bool
    {
        return $this->transientMap->contains($key);
    }

    /**
     * @param TValue $value
     *
     * @return TransientHashSetInterface<TValue>
     */
    public function add($value): TransientHashSetInterface
    {
        $this->transientMap->put($value, $value);

        return $this;
    }

    /**
     * @param TValue $value
     *
     * @return TransientHashSetInterface<TValue>
     */
    public function remove($value): TransientHashSetInterface
    {
        $this->transientMap->remove($value);

        return $this;
    }

    /**
     * @return PersistentHashSetInterface<TValue>
     */
    abstract public function persistent(): PersistentHashSetInterface;
}
