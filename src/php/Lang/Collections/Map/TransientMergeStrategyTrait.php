<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Map;

/**
 * Bulk-builds a merge through a transient instead of folding with `put()`,
 * so a merge allocates one persistent map rather than one per entry.
 *
 * Only maps whose `asTransient()` is a real transient may use this: a struct
 * throws from `asTransient()`, so it keeps the `put()`-folding default in
 * `AbstractPersistentMap::merge()`. The receiver's metadata survives either
 * way, because a transient carries the meta of the map it was opened from.
 *
 * @template TKey
 * @template TValue
 */
trait TransientMergeStrategyTrait
{
    /**
     * @param PersistentMapInterface<TKey, TValue> $other
     *
     * @return PersistentMapInterface<TKey, TValue>
     */
    public function merge(PersistentMapInterface $other): PersistentMapInterface
    {
        $transient = $this->asTransient();
        foreach ($other as $key => $value) {
            $transient->put($key, $value);
        }

        /** @var PersistentMapInterface<TKey, TValue> $result */
        $result = $transient->persistent();

        return $result;
    }

    /**
     * @return TransientMapWrapper<TKey, TValue>
     */
    abstract public function asTransient(): TransientMapWrapper;
}
