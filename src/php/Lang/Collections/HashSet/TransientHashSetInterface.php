<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\HashSet;

use Countable;

/**
 * @template V
 *
 * @extends AsTransientInterface<TransientHashMapInterface>
 */
interface TransientHashSetInterface extends Countable
{
    /**
     * @param V $value
     */
    public function contains($value): bool;

    /**
     * @param V $value
     */
    public function add($value): TransientHashSetInterface;

    /**
     * @param V $value
     */
    public function remove($value): TransientHashSetInterface;

    public function persistent(): PersistentHashSetInterface;
}
