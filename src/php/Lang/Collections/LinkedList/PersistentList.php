<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\LinkedList;

use IteratorAggregate;
use Phel\Lang\AbstractType;
use Phel\Lang\Collections\HashMap\PersistentHashMapInterface;
use Phel\Lang\EqualizerInterface;
use Phel\Lang\HasherInterface;
use Phel\Lang\TypeFactory;
use RuntimeException;
use Traversable;

/**
 * @template T
 * @implements PersistentListInterface<T>
 * @implements IteratorAggregate<T>
 * @extends AbstractType<PersistentList<T>>
 */
class PersistentList extends AbstractType implements PersistentListInterface, IteratorAggregate
{
    private EqualizerInterface $equalizer;
    private HasherInterface $hasher;
    private ?PersistentHashMapInterface $meta;
    /** @var T */
    private $first;
    /** @var PersistentList<T>|EmptyList<T> */
    private $rest;
    private int $count;
    private int $hashCache = 0;

    /**
     * @param T $first
     * @param PersistentList<T>|EmptyList<T> $rest
     */
    public function __construct(HasherInterface $hasher, EqualizerInterface $equalizer, ?PersistentHashMapInterface $meta, $first, $rest, int $count)
    {
        $this->hasher = $hasher;
        $this->equalizer = $equalizer;
        $this->meta = $meta;
        $this->first = $first;
        $this->rest = $rest;
        $this->count = $count;
    }

    /**
     * @template TT
     *
     * @return EmptyList<TT>
     */
    public static function empty(HasherInterface $hasher, EqualizerInterface $equalizer): EmptyList
    {
        return new EmptyList($hasher, $equalizer, null);
    }

    /**
     * @template TT
     *
     * @param TT[] $values
     *
     * @return PersistentList<TT>|EmptyList<TT>
     */
    public static function fromArray(HasherInterface $hasher, EqualizerInterface $equalizer, array $values)
    {
        /** @var EmptyList<TT> $result */
        $result = self::empty($hasher, $equalizer);
        for ($i = count($values) - 1; $i >= 0; $i--) {
            /** @var PersistentList<TT> $result */
            $result = $result->prepend($values[$i]);
        }

        return $result;
    }

    public function getMeta(): PersistentHashMapInterface
    {
        if ($this->meta) {
            return $this->meta;
        }

        return TypeFactory::getInstance()->emptyPersistentHashMap();
    }

    public function withMeta(?PersistentHashMapInterface $meta)
    {
        return new PersistentList($this->hasher, $this->equalizer, $meta, $this->first, $this->rest, $this->count);
    }

    /**
     * @param T $value
     */
    public function prepend($value): PersistentListInterface
    {
        return new self($this->hasher, $this->equalizer, $this->meta, $value, $this, $this->count + 1);
    }

    public function pop(): PersistentListInterface
    {
        return $this->rest;
    }

    public function count(): int
    {
        return $this->count;
    }

    /**
     * @return T
     */
    public function get(int $i)
    {
        $list = $this;
        for ($j = 0; $j < $this->count; $j++) {
            if ($j === $i) {
                /** @var T $result */
                $result = $list->first();
                return $result;
            }

            $list = $list->rest();
        }

        throw new RuntimeException('Index out of bounds');
    }

    public function equals($other): bool
    {
        if (!$other instanceof PersistentList) {
            return false;
        }

        if ($this->count !== $other->count()) {
            return false;
        }

        $s = $this;
        $ms = $other;
        for ($s = $this; $s != null; $s = $s->cdr(), $ms = $ms->cdr()) {
            /** @var PersistentList $s */
            /** @var ?PersistentList $ms */
            if ($ms === null || !$this->equalizer->equals($s->first(), $ms->first())) {
                return false;
            }
        }

        return true;
    }

    public function hash(): int
    {
        if ($this->hashCache === 0) {
            $this->hashCache = 1;
            foreach ($this as $value) {
                $this->hashCache = 31 * $this->hashCache + $this->hasher->hash($value);
            }
        }

        return $this->hashCache;
    }

    /**
     * @return Traversable<T>
     */
    public function getIterator(): Traversable
    {
        for ($s = $this; $s != null; $s = $s->cdr()) {
            /** @var PersistentList<T> $s */
            /** @var T $first  */
            $first = $s->first();
            yield $first;
        }
    }

    public function first()
    {
        return $this->first;
    }

    /**
     * @return PersistentList<T>|EmptyList<T>
     */
    public function rest()
    {
        return $this->rest;
    }

    /**
     * @return PersistentList<T>|EmptyList<T>|null
     */
    public function cdr()
    {
        if ($this->count === 1) {
            return null;
        }

        return $this->rest;
    }
}
