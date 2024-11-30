<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Map;

use IteratorAggregate;

/**
 * @template TKey
 * @template TValue
 */
interface HashMapNodeInterface extends IteratorAggregate
{
    /**
     * @param TKey $key
     * @param TValue $value
     */
    public function put(int $shift, int $hash, mixed $key, mixed $value, Box $addedLeaf): self;

    /**
     * @param TKey $key
     */
    public function remove(int $shift, int $hash, mixed $key): ?self;

    /**
     * @template TDefault
     *
     * @param TKey $key
     * @param ?TDefault $notFound
     *
     * @return TValue|TDefault
     */
    public function find(int $shift, int $hash, mixed $key, $notFound);
}
