<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\HashSet;

use Countable;
use Phel\Lang\ContainsInterface;

/**
 * @template V
 *
 * @extends ContainsInterface<V>
 */
interface TransientHashSetInterface extends Countable, ContainsInterface
{
    /**
     * @param V $value
     *
     * @return self<V>
     */
    public function add(mixed $value): self;

    /**
     * @param V $value
     *
     * @return self<V>
     */
    public function remove(mixed $value): self;

    /**
     * @return PersistentHashSetInterface<V>
     */
    public function persistent(): PersistentHashSetInterface;
}
