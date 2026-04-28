<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\SortedSet;

use Phel\Lang\AbstractType;
use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\SortedMap\PersistentSortedMap;
use Phel\Lang\HasherInterface;
use Traversable;

/**
 * @template V
 *
 * @implements PersistentHashSetInterface<V>
 *
 * @extends AbstractType<PersistentSortedSet<V>>
 */
final class PersistentSortedSet extends AbstractType implements PersistentHashSetInterface
{
    private int $hashCache = 0;

    public function __construct(
        private readonly HasherInterface $hasher,
        private readonly ?PersistentMapInterface $meta,
        private readonly PersistentMapInterface $map,
    ) {}

    /**
     * @param V $key
     *
     * @return ?V
     */
    public function __invoke(mixed $key)
    {
        return $this->map->find($key);
    }

    public function getMeta(): ?PersistentMapInterface
    {
        return $this->meta;
    }

    public function withMeta(?PersistentMapInterface $meta): static
    {
        return new self($this->hasher, $meta, $this->map);
    }

    /**
     * @param V $key
     */
    public function contains($key): bool
    {
        return $this->map->contains($key);
    }

    /**
     * @param V $value
     */
    public function add($value): PersistentHashSetInterface
    {
        $newMap = $this->map->put($value, $value);
        if ($newMap === $this->map) {
            return $this;
        }

        /** @var PersistentSortedMap $newMap */
        return new self($this->hasher, $this->meta, $newMap);
    }

    /**
     * @param V $value
     */
    public function remove($value): PersistentHashSetInterface
    {
        $newMap = $this->map->remove($value);
        if ($newMap === $this->map) {
            return $this;
        }

        return new self($this->hasher, $this->meta, $newMap);
    }

    public function count(): int
    {
        return $this->map->count();
    }

    public function equals(mixed $other): bool
    {
        if (!$other instanceof PersistentHashSetInterface) {
            return false;
        }

        if ($this->count() !== $other->count()) {
            return false;
        }

        foreach ($this as $value) {
            if (!$other->contains($value)) {
                return false;
            }
        }

        return true;
    }

    public function hash(): int
    {
        if ($this->hashCache === 0) {
            foreach ($this->map as $value) {
                $this->hashCache += $this->hasher->hash($value);
            }
        }

        return $this->hashCache;
    }

    public function getIterator(): Traversable
    {
        foreach ($this->map as $value) {
            yield $value;
        }
    }

    public function asTransient(): TransientSortedSet
    {
        return new TransientSortedSet($this->hasher, $this->map->asTransient());
    }

    public function toPhpArray(): array
    {
        return iterator_to_array($this);
    }

    /**
     * @param array<int, mixed> $xs
     */
    public function concat($xs): PersistentHashSetInterface
    {
        $map = $this->asTransient();
        foreach ($xs as $x) {
            $map->add($x);
        }

        return $map->persistent();
    }
}
