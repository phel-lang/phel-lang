<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Map;

use IteratorAggregate;

/**
 * @template TKey
 * @template TValue
 *
 * @extends IteratorAggregate<TKey, TValue>
 */
interface HashMapNodeInterface extends IteratorAggregate
{
    /**
     * @param TKey   $key
     * @param TValue $value
     *
     * @return self<TKey, TValue>
     */
    public function put(int $shift, int $hash, mixed $key, mixed $value, Box $addedLeaf): self;

    /**
     * @param TKey $key
     *
     * @return self<TKey, TValue>|null
     */
    public function remove(int $shift, int $hash, mixed $key): ?self;

    /**
     * Looks the key up, returning $notFound when it is absent.
     *
     * $notFound is a caller-chosen sentinel, so it must stay a distinct
     * template parameter: `null` (the map's own default) and the
     * `PersistentHashMap::getNotFound()` stdClass (used by `contains()` to
     * tell "absent" from "present but null") have to survive as separate
     * types in the return union.
     *
     * @template TDefault
     *
     * @param TKey     $key
     * @param TDefault $notFound
     *
     * @return TDefault|TValue
     */
    public function find(int $shift, int $hash, mixed $key, $notFound);
}
