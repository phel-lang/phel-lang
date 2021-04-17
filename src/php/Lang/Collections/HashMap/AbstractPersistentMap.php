<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\HashMap;

use Phel\Lang\AbstractType;
use Phel\Lang\EqualizerInterface;
use Phel\Lang\HasherInterface;
use RuntimeException;

/**
 * @template K
 * @template V
 *
 * @implements PersistentHashMapInterface<K, V>
 * @extends AbstractType<PersistentHashMap<K, V>>
 */
abstract class AbstractPersistentMap extends AbstractType implements PersistentHashMapInterface
{
    protected EqualizerInterface $equalizer;
    protected HasherInterface $hasher;
    protected ?PersistentHashMapInterface $meta;
    private int $hashCache = 0;

    public function __construct(HasherInterface $hasher, EqualizerInterface $equalizer, ?PersistentHashMapInterface $meta)
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

    public function getMeta(): ?PersistentHashMapInterface
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
        if (!$other instanceof PersistentHashMapInterface) {
            return false;
        }

        if ($this->count() !== $other->count()) {
            return false;
        }

        foreach ($this as $key => $value) {
            if (!$other->containsKey($key)) {
                return false;
            }

            if (!$this->equalizer->equals($value, $other->find($key))) {
                return false;
            }
        }

        return true;
    }

    public function merge(PersistentHashMapInterface $other): PersistentHashMapInterface
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
    public function offsetGet($offset)
    {
        return $this->find($offset);
    }

    /**
     * @param K $offset
     */
    public function offsetExists($offset): bool
    {
        return $this->containsKey($offset);
    }

    public function offsetSet($offset, $value): void
    {
        throw new RuntimeException('Method offsetSet is not supported on PersistentHashMap');
    }

    public function offsetUnset($offset): void
    {
        throw new RuntimeException('Method offsetUnset is not supported on PersistentHashMap');
    }
}
