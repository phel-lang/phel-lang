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
 * @template TValue
 *
 * @implements PersistentHashSetInterface<TValue>
 *
 * @extends AbstractType<PersistentHashSet<TValue>>
 */
final class PersistentHashSet extends AbstractType implements PersistentHashSetInterface
{
    private ?int $hashCache = null;

    /**
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     * @param PersistentMapInterface<TValue, TValue>    $map
     */
    public function __construct(
        private readonly HasherInterface $hasher,
        private readonly ?PersistentMapInterface $meta,
        private readonly PersistentMapInterface $map,
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
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     */
    public function withMeta(?PersistentMapInterface $meta): static
    {
        return new self($this->hasher, $meta, $this->map);
    }

    /**
     * @param TValue $key
     */
    public function contains($key): bool
    {
        return $this->map->contains($key);
    }

    /**
     * @param TValue $value
     *
     * @return PersistentHashSetInterface<TValue>
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
     * @param TValue $value
     *
     * @return PersistentHashSetInterface<TValue>
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
     * @return TransientHashSet<TValue>
     */
    public function asTransient(): TransientHashSet
    {
        return new TransientHashSet($this->hasher, $this->map->asTransient());
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
        $map = $this->asTransient();
        foreach ($xs as $x) {
            $map->add($x);
        }

        return $map->persistent();
    }
}
