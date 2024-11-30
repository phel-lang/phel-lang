<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Map;

use Phel\Lang\EqualizerInterface;
use Phel\Lang\HasherInterface;
use RuntimeException;
use Traversable;

use function count;

/**
 * Map implementation based on a single array. The array stores the key value pair directly.
 *
 * This implementation is only appropriate for very small maps, since the array is copied
 * every time the map changes.
 *
 * @template K
 * @template V
 *
 * @extends AbstractPersistentMap<K, V>
 */
final class PersistentArrayMap extends AbstractPersistentMap
{
    public const MAX_SIZE = 16;

    public function __construct(
        HasherInterface $hasher,
        EqualizerInterface $equalizer,
        ?PersistentMapInterface $meta,
        private array $array,
    ) {
        parent::__construct($hasher, $equalizer, $meta);
    }

    public static function empty(HasherInterface $hasher, EqualizerInterface $equalizer): self
    {
        return new self($hasher, $equalizer, null, []);
    }

    public static function fromArray(HasherInterface $hasher, EqualizerInterface $equalizer, array $kvs): PersistentMapInterface
    {
        if (count($kvs) % 2 !== 0) {
            throw new RuntimeException('A even number of elements must be provided');
        }

        $result = self::empty($hasher, $equalizer)->asTransient();
        for ($i = 0, $l = count($kvs); $i < $l; $i += 2) {
            $result->put($kvs[$i], $kvs[$i + 1]);
        }

        return $result->persistent();
    }

    public function withMeta(?PersistentMapInterface $meta): self
    {
        return new self($this->hasher, $this->equalizer, $meta, $this->array);
    }

    public function contains($key): bool
    {
        return $this->findIndex($key) !== false;
    }

    public function put($key, $value): PersistentMapInterface
    {
        $index = $this->findIndex($key);

        if ($index !== false && $this->equalizer->equals($this->array[$index + 1], $value)) {
            return $this;
        }

        if ($index === false && $this->count() >= self::MAX_SIZE) {
            return PersistentHashMap::fromArray($this->hasher, $this->equalizer, $this->array)->put($key, $value);
        }

        $newArray = $this->array;
        if ($index === false) {
            $newArray[] = $key;
            $newArray[] = $value;
        } else {
            $newArray[$index + 1] = $value;
        }

        return new self($this->hasher, $this->equalizer, $this->meta, $newArray);
    }

    public function remove($key): self
    {
        $index = $this->findIndex($key);

        if ($index === false) {
            return $this;
        }

        $newArray = $this->array;
        array_splice($newArray, $index, 2);

        return new self($this->hasher, $this->equalizer, $this->meta, $newArray);
    }

    public function find($key)
    {
        $index = $this->findIndex($key);
        if ($index === false) {
            return null;
        }

        return $this->array[$index + 1];
    }

    public function count(): int
    {
        return (int) (count($this->array) / 2);
    }

    public function getIterator(): Traversable
    {
        for ($i = 0, $cnt = count($this->array); $i < $cnt; $i += 2) {
            yield $this->array[$i] => $this->array[$i + 1];
        }
    }

    public function asTransient(): TransientMapWrapper
    {
        return new TransientMapWrapper(
            new TransientArrayMap(
                $this->hasher,
                $this->equalizer,
                $this->array,
            ),
        );
    }

    /**
     * @param K $key
     */
    private function findIndex(mixed $key): int|false
    {
        for ($i = 0, $cnt = count($this->array); $i < $cnt; $i += 2) {
            $k = $this->array[$i];
            if ($this->equalizer->equals($k, $key)) {
                return $i;
            }
        }

        return false;
    }
}
