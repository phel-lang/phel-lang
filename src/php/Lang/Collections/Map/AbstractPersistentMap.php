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
 * @template K
 * @template V
 *
 * @implements PersistentMapInterface<K, V>
 *
 * @extends AbstractType<AbstractPersistentMap<K, V>>
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
     * @param K $key
     *
     * @return ?V
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
     * @param PersistentMapInterface<K, V> $other
     *
     * @return PersistentMapInterface<K, V>
     */
    public function merge(PersistentMapInterface $other): PersistentMapInterface
    {
        if ($this instanceof PersistentHashMap || $this instanceof PersistentArrayMap) {
            $tm = $this->asTransient();
            foreach ($other as $k => $v) {
                $tm->put($k, $v);
            }

            /** @var PersistentMapInterface<K, V> $result */
            $result = $tm->persistent();

            return $result;
        }

        $m = $this;
        foreach ($other as $k => $v) {
            $m = $m->put($k, $v);
        }

        return $m;
    }

    /**
     * @param K $offset
     *
     * @return V|null
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->find($offset);
    }

    /**
     * @param K $offset
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
