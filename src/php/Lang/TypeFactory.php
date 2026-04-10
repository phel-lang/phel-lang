<?php

declare(strict_types=1);

namespace Phel\Lang;

use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;
use Phel\Lang\Collections\HashSet\TransientHashSet;
use Phel\Lang\Collections\LinkedList\PersistentList;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentArrayMap;
use Phel\Lang\Collections\Map\PersistentHashMap;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\SortedMap\PersistentSortedMap;
use Phel\Lang\Collections\SortedSet\TransientSortedSet;
use Phel\Lang\Collections\Vector\PersistentVector;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;

use function count;

final class TypeFactory
{
    private readonly HasherInterface $hasher;

    private readonly EqualizerInterface $equalizer;

    private static ?TypeFactory $instance = null;

    public function __construct()
    {
        $this->hasher = new Hasher();
        $this->equalizer = new Equalizer();
    }

    public static function getInstance(): self
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @param list<mixed> $kvs
     */
    public function persistentMapFromKVs(...$kvs): PersistentMapInterface
    {
        return $this->persistentMapFromArray($kvs);
    }

    public function persistentMapFromArray(array $kvs = []): PersistentMapInterface
    {
        if (count($kvs) <= PersistentArrayMap::MAX_SIZE) {
            return PersistentArrayMap::fromArray($this->hasher, $this->equalizer, $kvs);
        }

        return PersistentHashMap::fromArray($this->hasher, $this->equalizer, $kvs);
    }

    public function persistentHashSetFromArray(array $values): PersistentHashSetInterface
    {
        $set = new TransientHashSet($this->hasher, $this->persistentMapFromArray()->asTransient());
        foreach ($values as $value) {
            $set->add($value);
        }

        return $set->persistent();
    }

    public function persistentListFromArray(array $values): PersistentListInterface
    {
        return PersistentList::fromArray($this->hasher, $this->equalizer, $values);
    }

    public function persistentVectorFromArray(array $values): PersistentVectorInterface
    {
        return PersistentVector::fromArray($this->hasher, $this->equalizer, $values);
    }

    /**
     * @param ?callable(mixed, mixed): int $comparator
     */
    public function persistentSortedMapFromArray(array $kvs = [], ?callable $comparator = null): PersistentMapInterface
    {
        return PersistentSortedMap::fromArray($this->hasher, $this->equalizer, $kvs, $comparator);
    }

    /**
     * @param ?callable(mixed, mixed): int $comparator
     */
    public function persistentSortedSetFromArray(array $values = [], ?callable $comparator = null): PersistentHashSetInterface
    {
        $map = PersistentSortedMap::empty($this->hasher, $this->equalizer, $comparator);
        $transient = new TransientSortedSet($this->hasher, $map->asTransient());
        foreach ($values as $value) {
            $transient->add($value);
        }

        return $transient->persistent();
    }

    public function getEqualizer(): EqualizerInterface
    {
        return $this->equalizer;
    }

    public function getHasher(): HasherInterface
    {
        return $this->hasher;
    }
}
