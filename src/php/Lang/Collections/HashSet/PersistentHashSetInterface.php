<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\HashSet;

use Countable;

/**
 * @template V
 */
interface PersistentHashSetInterface extends Countable
{
    /**
     * @param V $value
     */
    public function contains($value): bool;

    /**
     * @param V $value
     */
    public function add($value): PersistentHashSetInterface;

    /**
     * @param V $value
     */
    public function remove($value): PersistentHashSetInterface;
}
