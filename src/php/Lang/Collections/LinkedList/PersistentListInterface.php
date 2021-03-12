<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\LinkedList;

use Countable;
use Phel\Lang\SeqInterface;

/**
 * @template T
 * @template-extends SeqInterface<T, PersistentListInterface<T>>
 */
interface PersistentListInterface extends SeqInterface, Countable
{

    /**
     * @param T $value
     *
     * @return PersistentListInterface<T>
     */
    public function prepend($value): PersistentListInterface;

    /**
     * @return T
     */
    public function get(int $i);

    /**
     * @return PersistentListInterface<T>
     */
    public function pop(): PersistentListInterface;
}
