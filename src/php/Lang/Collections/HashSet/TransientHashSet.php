<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\HashSet;

use Phel\Lang\Collections\Map\TransientMapInterface;
use Phel\Lang\HasherInterface;
use Stringable;

/**
 * @template TValue
 *
 * @implements TransientHashSetInterface<TValue>
 */
final readonly class TransientHashSet implements TransientHashSetInterface, Stringable
{
    /**
     * @param TransientMapInterface<TValue, TValue> $transientMap
     */
    public function __construct(
        private HasherInterface $hasher,
        private TransientMapInterface $transientMap,
    ) {}

    public function __toString(): string
    {
        return '<TransientSet count=' . $this->transientMap->count() . '>';
    }

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
    public function persistent(): PersistentHashSetInterface
    {
        return new PersistentHashSet($this->hasher, null, $this->transientMap->persistent());
    }
}
