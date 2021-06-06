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
 * @extends ContainsInferface<K>
 */
interface TransientMapInterface extends Countable, ArrayAccess, ContainsInterface
{
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
