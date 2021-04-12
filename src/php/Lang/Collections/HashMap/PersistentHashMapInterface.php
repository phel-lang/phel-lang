<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\HashMap;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use Phel\Lang\Table;
use Phel\Lang\TypeInterface;

/**
 * @template K
 * @template V
 *
 * @extends IteratorAggregate<K, V>
 * @extends ArrayAccess<K,V>
 * @extends TypeInterface<PersistentHashMapInterface<K, V>>
 */
interface PersistentHashMapInterface extends TypeInterface, Countable, IteratorAggregate, ArrayAccess
{
    /**
     * @param K $key
     */
    public function containsKey($key): bool;

    /**
     * @param K $key
     * @param V $value
     */
    public function put($key, $value): PersistentHashMapInterface;

    /**
     * @param K $key
     */
    public function remove($key): PersistentHashMapInterface;

    /**
     * @param K $key
     *
     * @return V
     */
    public function find($key);

    public function toTable(): Table;

    public function merge(PersistentHashMapInterface $other): PersistentHashMapInterface;
}
