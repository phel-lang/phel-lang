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
 * @extends ContainsInterface<K>
 */
interface TransientMapInterface extends Countable, ArrayAccess, ContainsInterface
{
    /**
     * @param K $key
     * @param V $value
     */
    public function put(mixed $key, mixed $value): self;

    /**
     * @param K $key
     */
    public function remove(mixed $key): self;

    /**
     * @param K $key
     *
     * @return V
     */
    public function find(mixed $key);

    public function persistent(): PersistentMapInterface;
}
