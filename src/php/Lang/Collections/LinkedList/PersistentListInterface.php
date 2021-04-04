<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\LinkedList;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use Phel\Lang\ConcatInterface;
use Phel\Lang\ConsInterface;
use Phel\Lang\SeqInterface;
use Phel\Lang\TypeInterface;

/**
 * @template TValue
 *
 * @extends SeqInterface<TValue, PersistentListInterface<TValue>>
 * @extends TypeInterface<PersistentListInterface<TValue>>
 * @extends IteratorAggregate<TValue>
 * @extends ConsInterface<PersistentListInterface<TValue>>
 * @extends ArrayAccess<TValue>
 * @extends ConcatInterface<PersistentListInterface<TValue>>
 */
interface PersistentListInterface extends TypeInterface, SeqInterface, IteratorAggregate, Countable, ConsInterface, ArrayAccess, ConcatInterface
{

    /**
     * @param TValue $value
     *
     * @return PersistentListInterface<TValue>
     */
    public function prepend($value): PersistentListInterface;

    /**
     * @return TValue
     */
    public function get(int $i);

    /**
     * @return PersistentListInterface<TValue>
     */
    public function pop(): PersistentListInterface;
}
