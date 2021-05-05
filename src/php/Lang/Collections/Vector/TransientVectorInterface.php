<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Vector;

use ArrayAccess;
use Countable;

/**
 * @template T
 */
interface TransientVectorInterface extends Countable, ArrayAccess
{
    public const BRANCH_FACTOR = 32;
    public const INDEX_MASK = self::BRANCH_FACTOR - 1;
    public const SHIFT = 5;

    /**
     * @param T $value
     */
    public function append($value): TransientVectorInterface;

    /**
     * @param T $value
     */
    public function update(int $i, $value): TransientVectorInterface;

    /**
     * @return T
     */
    public function get(int $i);

    public function pop(): TransientVectorInterface;

    public function persistent(): PersistentVectorInterface;
}
