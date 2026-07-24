<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\HashSet;

use Countable;
use Phel\Lang\ContainsInterface;

/**
 * @template TValue
 *
 * @extends ContainsInterface<TValue>
 */
interface TransientHashSetInterface extends Countable, ContainsInterface
{
    /**
     * @param TValue $value
     *
     * @return self<TValue>
     */
    public function add(mixed $value): self;

    /**
     * @param TValue $value
     *
     * @return self<TValue>
     */
    public function remove(mixed $value): self;

    /**
     * @return PersistentHashSetInterface<TValue>
     */
    public function persistent(): PersistentHashSetInterface;
}
