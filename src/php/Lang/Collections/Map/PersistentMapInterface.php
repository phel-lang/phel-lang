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
 * @template TKey
 * @template TValue
 *
 * @extends IteratorAggregate<TKey, TValue>
 * @extends ArrayAccess<TKey,TValue>
 * @extends AsTransientInterface<TransientMapInterface<TKey, TValue>>
 * @extends ContainsInterface<TKey>
 */
interface PersistentMapInterface extends TypeInterface, Countable, IteratorAggregate, ArrayAccess, AsTransientInterface, FnInterface, ContainsInterface
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
     * @param self<TKey, TValue> $other
     *
     * @return self<TKey, TValue>
     */
    public function merge(self $other): self;
}
