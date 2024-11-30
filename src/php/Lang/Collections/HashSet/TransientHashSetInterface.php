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
     */
    public function add(mixed $value): self;

    /**
     * @param V $value
     */
    public function remove(mixed $value): self;

    public function persistent(): PersistentHashSetInterface;
}
