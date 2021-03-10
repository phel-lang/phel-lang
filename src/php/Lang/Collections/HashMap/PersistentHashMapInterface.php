<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\HashMap;

use Countable;

/**
 * @template K
 * @template V
 */
interface PersistentHashMapInterface extends Countable
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
}
