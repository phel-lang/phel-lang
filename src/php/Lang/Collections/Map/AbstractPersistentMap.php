<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Map;

use Phel\Lang\AbstractType;
use Phel\Lang\Collections\Exceptions\MethodNotSupportedException;
use Phel\Lang\EqualizerInterface;
use Phel\Lang\HasherInterface;

use function is_float;
use function is_nan;

/**
 * @template TKey
 * @template TValue
 *
 * @implements PersistentMapInterface<TKey, TValue>
 *
 * @extends AbstractType<AbstractPersistentMap<TKey, TValue>>
 */
abstract class AbstractPersistentMap extends AbstractType implements PersistentMapInterface
{
    private ?int $hashCache = null;

    /**
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     */
    public function __construct(
        protected HasherInterface $hasher,
        protected EqualizerInterface $equalizer,
        protected ?PersistentMapInterface $meta,
    ) {}

    /**
     * @param TKey $key
     *
     * @return ?TValue
     */
    public function __invoke(mixed $key)
    {
        return $this->find($key);
    }

    /**
     * @return PersistentMapInterface<mixed, mixed>|null
     */
    public function getMeta(): ?PersistentMapInterface
    {
        return $this->meta;
    }

    public function hash(): int
    {
        if ($this->hashCache !== null) {
            return $this->hashCache;
        }

        return $this->hashCache = $this->hasher->unorderedKeyedHash($this);
    }

    public function equals(mixed $other): bool
    {
        if ($this === $other) {
            return true;
        }

        if (!$other instanceof PersistentMapInterface) {
            return false;
        }

        if ($this->count() !== $other->count()) {
            return false;
        }

        foreach ($this as $key => $value) {
            // A NaN key is never `=` to itself, so a map carrying one is
            // unequal to any distinct map (identical maps short-circuit via
            // `===` before reaching here). Key *lookup* still matches NaN.
            if (is_float($key) && is_nan($key)) {
                return false;
            }

            if (!$other->contains($key)) {
                return false;
            }

            if (!$this->equalizer->equals($value, $other->find($key))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Folds the other map in one `put()` at a time. This works for every
     * implementation, including those with no transient at all (a struct's
     * `asTransient()` throws), and it threads the receiver's metadata through
     * each copy. Implementations backed by a real transient trade both
     * properties for fewer allocations via `TransientMergeStrategyTrait`.
     *
     * @param PersistentMapInterface<TKey, TValue> $other
     *
     * @return PersistentMapInterface<TKey, TValue>
     */
    public function merge(PersistentMapInterface $other): PersistentMapInterface
    {
        $result = $this;
        foreach ($other as $key => $value) {
            $result = $result->put($key, $value);
        }

        return $result;
    }

    /**
     * @param TKey $offset
     *
     * @return TValue|null
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->find($offset);
    }

    /**
     * @param TKey $offset
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->contains($offset);
    }

    public function offsetSet($offset, $value): void
    {
        throw new MethodNotSupportedException('Method offsetSet is not supported on PersistentMap');
    }

    public function offsetUnset($offset): void
    {
        throw new MethodNotSupportedException('Method offsetUnset is not supported on PersistentMap');
    }
}
