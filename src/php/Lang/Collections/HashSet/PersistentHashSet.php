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
 * @template V
 *
 * @implements PersistentHashSetInterface<V>
 *
 * @extends AbstractType<PersistentHashSet<V>>
 */
final class PersistentHashSet extends AbstractType implements PersistentHashSetInterface
{
    /**
     * Keeps the rolling hash accumulator inside a 32-bit unsigned range so
     * the running sum can never silently promote to float (which would throw
     * a TypeError when assigned to `?int $hashCache` under strict_types for
     * large sets). Kept in lockstep with vector, list, queue and map hashing.
     */
    private const int HASH_MASK = 0xFFFFFFFF;

    private ?int $hashCache = null;

    /**
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     * @param PersistentMapInterface<V, V>              $map
     */
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

    /**
     * @return PersistentMapInterface<mixed, mixed>|null
     */
    public function getMeta(): ?PersistentMapInterface
    {
        return $this->meta;
    }

    /**
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     */
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
     *
     * @return PersistentHashSetInterface<V>
     */
    public function add($value): PersistentHashSetInterface
    {
        $newMap = $this->map->put($value, $value);
        if ($newMap === $this->map) {
            return $this;
        }

        return new self($this->hasher, $this->meta, $newMap);
    }

    /**
     * @param V $value
     *
     * @return PersistentHashSetInterface<V>
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
            // A NaN element is never `=` to itself, so a set carrying one is
            // unequal to any distinct set (identical sets short-circuit via
            // `===` before reaching here). Membership lookup still matches NaN.
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

        $hash = 0;
        foreach ($this->map as $value) {
            $hash = ($hash + $this->hasher->hash($value)) & self::HASH_MASK;
        }

        return $this->hashCache = $hash;
    }

    /**
     * @return Traversable<int, V>
     */
    public function getIterator(): Traversable
    {
        foreach ($this->map as $value) {
            yield $value;
        }
    }

    /**
     * @return TransientHashSet<V>
     */
    public function asTransient(): TransientHashSet
    {
        return new TransientHashSet($this->hasher, $this->map->asTransient());
    }

    /**
     * @return array<int, V>
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
     * @return PersistentHashSetInterface<V>
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
