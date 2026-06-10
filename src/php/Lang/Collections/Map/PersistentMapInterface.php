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
 * @extends AsTransientInterface<TransientMapInterface<K, V>>
 * @extends ContainsInterface<K>
 */
interface PersistentMapInterface extends TypeInterface, Countable, IteratorAggregate, ArrayAccess, AsTransientInterface, FnInterface, ContainsInterface
{
    /**
     * @param K $key
     * @param V $value
     *
     * @return self<K, V>
     */
    public function put(mixed $key, mixed $value): self;

    /**
     * @param K $key
     *
     * @return self<K, V>
     */
    public function remove(mixed $key): self;

    /**
     * @param K $key
     *
     * @return V|null Value for $key, or null when the key is absent
     */
    public function find(mixed $key);

    /**
     * @param self<K, V> $other
     *
     * @return self<K, V>
     */
    public function merge(self $other): self;
}
