<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Vector;

use Countable;

/**
 * @template T
 */
interface PersistentVectorInterface extends Countable
{
    public const BRANCH_FACTOR = 32;
    public const INDEX_MASK = self::BRANCH_FACTOR - 1;
    public const SHIFT = 5;

    /**
     * @param T $value
     */
    public function append($value): PersistentVectorInterface;

    /**
     * @param T $value
     */
    public function update(int $i, $value): PersistentVectorInterface;

    /**
     * @return T
     */
    public function get(int $i);

    public function pop(): PersistentVectorInterface;
}
