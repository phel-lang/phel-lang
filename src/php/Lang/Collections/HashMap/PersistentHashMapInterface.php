<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\HashMap;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use Phel\Lang\Collections\AsTransientInterface;
use Phel\Lang\TypeInterface;

/**
 * @template K
 * @template V
 *
 * @extends IteratorAggregate<K, V>
 * @extends ArrayAccess<K,V>
 * @extends TypeInterface<PersistentHashMapInterface<K, V>>
 * @extends AsTransientInterface<TransientHashMapInterface>
 */
interface PersistentHashMapInterface extends TypeInterface, Countable, IteratorAggregate, ArrayAccess, AsTransientInterface
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

    public function merge(PersistentHashMapInterface $other): PersistentHashMapInterface;
}
