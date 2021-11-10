<?php

declare(strict_types=1);

namespace Phel\Lang;

use Phel\Lang\Collections\HashSet\PersistentHashSet;
use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;
use Phel\Lang\Collections\HashSet\TransientHashSet;
use Phel\Lang\Collections\LinkedList\EmptyList;
use Phel\Lang\Collections\LinkedList\PersistentList;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentArrayMap;
use Phel\Lang\Collections\Map\PersistentHashMap;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVector;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;

class TypeFactory
{
    private HasherInterface $hasher;
    private EqualizerInterface $equalizer;
    private static ?TypeFactory $instance = null;

    public function __construct()
    {
        $this->hasher = new Hasher();
        $this->equalizer = new Equalizer();
    }

    public static function getInstance(): TypeFactory
    {
        if (self::$instance === null) {
            self::$instance = new TypeFactory();
        }

        return self::$instance;
    }

    public function emptyPersistentMap(): PersistentMapInterface
    {
        return PersistentArrayMap::empty($this->hasher, $this->equalizer);
    }

    /**
     * @param mixed[] $kvs
     */
    public function persistentMapFromKVs(...$kvs): PersistentMapInterface
    {
        return $this->persistentMapFromArray($kvs);
    }

    public function persistentMapFromArray(array $kvs): PersistentMapInterface
    {
        if (count($kvs) <= PersistentArrayMap::MAX_SIZE) {
            return PersistentArrayMap::fromArray($this->hasher, $this->equalizer, $kvs);
        }

        return PersistentHashMap::fromArray($this->hasher, $this->equalizer, $kvs);
    }

    public function emptyPersistentHashSet(): PersistentHashSetInterface
    {
        return new PersistentHashSet($this->hasher, null, $this->emptyPersistentMap());
    }

    public function persistentHashSetFromArray(array $values): PersistentHashSetInterface
    {
        $set = new TransientHashSet($this->hasher, $this->emptyPersistentMap()->asTransient());
        foreach ($values as $value) {
            $set->add($value);
        }

        return $set->persistent();
    }

    public function emptyPersistentList(): EmptyList
    {
        return PersistentList::empty($this->hasher, $this->equalizer);
    }

    public function persistentListFromArray(array $values): PersistentListInterface
    {
        return PersistentList::fromArray($this->hasher, $this->equalizer, $values);
    }

    public function emptyPersistentVector(): PersistentVectorInterface
    {
        return PersistentVector::empty($this->hasher, $this->equalizer);
    }

    public function persistentVectorFromArray(array $values): PersistentVectorInterface
    {
        return PersistentVector::fromArray($this->hasher, $this->equalizer, $values);
    }

    /**
     * @template T
     *
     * @param T $value The initial value of the variable
     *
     * @return Variable<T>
     */
    public function variable($value): Variable
    {
        return new Variable(null, $value);
    }

    public function symbol(string $name): Symbol
    {
        return Symbol::create($name);
    }

    public function symbolForNamespace(?string $namespace, string $name): Symbol
    {
        return Symbol::createForNamespace($namespace, $name);
    }

    public function keyword(string $name): Keyword
    {
        return Keyword::create($name);
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
