<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Map;

use ArrayAccess;
use Countable;
use IteratorAggregate;

/**
 * @template K
 * @template V
 */
interface TransientMapInterface extends Countable, ArrayAccess, IteratorAggregate
{
    /**
     * @param K $key
     */
    public function containsKey($key): bool;

    /**
     * @param K $key
     * @param V $value
     */
    public function put($key, $value): self;

    /**
     * @param K $key
     */
    public function remove($key): self;

    /**
     * @param K $key
     *
     * @return V
     */
    public function find($key);

    public function persistent(): PersistentMapInterface;
}
