<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Map;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use Phel\Lang\Collections\AsTransientInterface;
use Phel\Lang\ContainsInterface;
use Phel\Lang\FnInterface;
use Phel\Lang\TypeInterface;

/**
 * @template K
 * @template V
 *
 * @extends IteratorAggregate<K, V>
 * @extends ArrayAccess<K,V>
 * @extends TypeInterface<PersistentMapInterface<K, V>>
 * @extends AsTransientInterface<TransientMapInterface>
 * @extends ContainsInterface<K>
 */
interface PersistentMapInterface extends TypeInterface, Countable, IteratorAggregate, ArrayAccess, AsTransientInterface, FnInterface, ContainsInterface
{
    /**
     * @param K $key
     * @param V $value
     */
    public function put($key, $value): PersistentMapInterface;

    /**
     * @param K $key
     */
    public function remove($key): PersistentMapInterface;

    /**
     * @param K $key
     *
     * @return V
     */
    public function find($key);

    public function merge(PersistentMapInterface $other): PersistentMapInterface;
}
