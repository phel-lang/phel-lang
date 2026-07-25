<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\HashSet;

use Phel\Lang\AbstractType;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\HasherInterface;
use Traversable;

use function is_float;
use function is_nan;

/**
 * Shared behaviour of the persistent set flavours: both are a thin facade over a
 * persistent map that stores each member as its own key, so membership, count,
 * equality, hashing and iteration are entirely the backing map's job and the
 * ordering lives there too.
 *
 * Subclasses own only what needs to name their own type: the copy-on-write
 * operations (`withMeta`, `add`, `remove`) and `asTransient`. Mirrors how
 * `SortedMap\PersistentSortedMap` and `Map\PersistentHashMap` share
 * `Map\AbstractPersistentMap`.
 *
 * @template TValue
 *
 * @implements PersistentHashSetInterface<TValue>
 *
 * @extends AbstractType<AbstractPersistentSet<TValue>>
 */
abstract class AbstractPersistentSet extends AbstractType implements PersistentHashSetInterface
{
    private ?int $hashCache = null;

    /**
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     * @param PersistentMapInterface<TValue, TValue>    $map
     */
    public function __construct(
        protected readonly HasherInterface $hasher,
        protected readonly ?PersistentMapInterface $meta,
        protected readonly PersistentMapInterface $map,
    ) {}

    /**
     * @param TValue $key
     *
     * @return ?TValue
     */
    public function __invoke(mixed $key)
    {
        return $this->map->find($key);
    }

    /**
     * @return PersistentMapInterface<mixed, mixed>|null
     */
    public function getMeta(): ?PersistentMapInterface
    {
        return $this->meta;
    }

    /**
     * @param TValue $key
     */
    public function contains($key): bool
    {
        return $this->map->contains($key);
    }

    public function count(): int
    {
        return $this->map->count();
    }

    public function equals(mixed $other): bool
    {
        if ($this === $other) {
            return true;
        }

        if (!$other instanceof PersistentHashSetInterface) {
            return false;
        }

        if ($this->count() !== $other->count()) {
            return false;
        }

        foreach ($this as $value) {
            // A NaN element is never `=` to itself, so a set carrying one is
            // unequal to any distinct set (identical sets short-circuit via
            // `===` before reaching here). Membership lookup still matches NaN
            // whenever the hasher or comparator orders it equal to itself.
            if (is_float($value) && is_nan($value)) {
                return false;
            }

            if (!$other->contains($value)) {
                return false;
            }
        }

        return true;
    }

    public function hash(): int
    {
        if ($this->hashCache !== null) {
            return $this->hashCache;
        }

        return $this->hashCache = $this->hasher->unorderedHash($this->map);
    }

    /**
     * @return Traversable<int, TValue>
     */
    public function getIterator(): Traversable
    {
        foreach ($this->map as $value) {
            yield $value;
        }
    }

    /**
     * @return array<int, TValue>
     */
    public function toPhpArray(): array
    {
        return iterator_to_array($this);
    }

    /**
     * Concatenates a value to the data structure.
     *
     * @param iterable<mixed> $xs The value to concatenate
     *
     * @return PersistentHashSetInterface<TValue>
     */
    public function concat($xs): PersistentHashSetInterface
    {
        $set = $this->asTransient();
        foreach ($xs as $x) {
            $set->add($x);
        }

        return $set->persistent();
    }
}
