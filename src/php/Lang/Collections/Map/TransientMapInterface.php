<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Map;

use ArrayAccess;
use Countable;
use Phel\Lang\ContainsInterface;

/**
 * @template K
 * @template V
 *
 * @extends ArrayAccess<K, V>
 * @extends ContainsInterface<K>
 */
interface TransientMapInterface extends Countable, ArrayAccess, ContainsInterface
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
     * @return PersistentMapInterface<K, V>
     */
    public function persistent(): PersistentMapInterface;
}
