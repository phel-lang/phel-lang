<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Vector;

use ArrayAccess;
use Countable;
use Phel\Lang\ContainsInterface;

/**
 * @template T
 *
 * @extends ArrayAccess<int, T>
 * @extends ContainsInterface<int>
 */
interface TransientVectorInterface extends Countable, ArrayAccess, ContainsInterface
{
    public const int BRANCH_FACTOR = 32;

    public const int INDEX_MASK = self::BRANCH_FACTOR - 1;

    public const int SHIFT = 5;

    /**
     * @param T $value
     */
    public function append(mixed $value): self;

    /**
     * @param T $value
     */
    public function update(int $i, mixed $value): self;

    /**
     * @return T
     */
    public function get(int $i);

    public function pop(): self;

    public function persistent(): PersistentVectorInterface;
}
