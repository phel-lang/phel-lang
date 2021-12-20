<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Map;

use Phel\Lang\AbstractType;
use Phel\Lang\Collections\Exceptions\MethodNotSupportedException;
use Phel\Lang\EqualizerInterface;
use Phel\Lang\HasherInterface;

/**
 * @template K
 * @template V
 *
 * @implements PersistentMapInterface<K, V>
 * @extends AbstractType<AbstractPersistentMap<K, V>>
 */
abstract class AbstractPersistentMap extends AbstractType implements PersistentMapInterface
{
    protected EqualizerInterface $equalizer;
    protected HasherInterface $hasher;
    protected ?PersistentMapInterface $meta;
    private int $hashCache = 0;

    public function __construct(HasherInterface $hasher, EqualizerInterface $equalizer, ?PersistentMapInterface $meta)
    {
        $this->equalizer = $equalizer;
        $this->hasher = $hasher;
        $this->meta = $meta;
    }

    /**
     * @param K $key
     *
     * @return ?V
     */
    public function __invoke($key)
    {
        return $this->find($key);
    }

    public function getMeta(): ?PersistentMapInterface
    {
        return $this->meta;
    }

    public function hash(): int
    {
        if ($this->hashCache === 0) {
            $this->hashCache = 1;
            foreach ($this as $key => $value) {
                $this->hashCache += $this->hasher->hash($key) ^ $this->hasher->hash($value);
            }
        }

        return $this->hashCache;
    }

    public function equals($other): bool
    {
        if (!$other instanceof PersistentMapInterface) {
            return false;
        }

        if ($this->count() !== $other->count()) {
            return false;
        }

        foreach ($this as $key => $value) {
            if (!$other->contains($key)) {
                return false;
            }

            if (!$this->equalizer->equals($value, $other->find($key))) {
                return false;
            }
        }

        return true;
    }

    public function merge(PersistentMapInterface $other): PersistentMapInterface
    {
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
    public function offsetGet($offset): mixed
    {
        return $this->find($offset);
    }

    /**
     * @param K $offset
     */
    public function offsetExists($offset): bool
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
