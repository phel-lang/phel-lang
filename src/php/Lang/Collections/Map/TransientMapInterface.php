<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Map;

use ArrayAccess;
use Countable;
use Phel\Lang\ContainsInterface;

/**
 * @template TKey
 * @template TValue
 *
 * @extends ArrayAccess<TKey, TValue>
 * @extends ContainsInterface<TKey>
 */
interface TransientMapInterface extends Countable, ArrayAccess, ContainsInterface
{
    /**
     * @param TKey   $key
     * @param TValue $value
     *
     * @return self<TKey, TValue>
     */
    public function put(mixed $key, mixed $value): self;

    /**
     * @param TKey $key
     *
     * @return self<TKey, TValue>
     */
    public function remove(mixed $key): self;

    /**
     * @param TKey $key
     *
     * @return TValue|null Value for $key, or null when the key is absent
     */
    public function find(mixed $key);

    /**
     * @return PersistentMapInterface<TKey, TValue>
     */
    public function persistent(): PersistentMapInterface;
}
