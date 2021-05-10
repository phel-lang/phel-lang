<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Vector;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use Phel\Lang\Collections\AsTransientInterface;
use Phel\Lang\ConcatInterface;
use Phel\Lang\ContainsInterface;
use Phel\Lang\FnInterface;
use Phel\Lang\PushInterface;
use Phel\Lang\SeqInterface;
use Phel\Lang\SliceInterface;
use Phel\Lang\TypeInterface;

/**
 * @template T
 *
 * @extends TypeInferface<PersistentVectorInterface<T>>
 * @extends SeqInterface<T, PersistentVectorInterface<T>>
 * @extends IteratorAggregate<T>
 * @extends ArrayAccess<T>
 * @extends ConcatInterface<PersistentVectorInterface<T>>
 * @extends PushInterface<PersistentVectorInterface<T>>
 * @extends AsTransientInterface<TransientVectorInterface>
 * @extends ContainsInterface<int>
 */
interface PersistentVectorInterface extends TypeInterface, SeqInterface, IteratorAggregate, Countable, ArrayAccess, ConcatInterface, PushInterface, SliceInterface, AsTransientInterface, FnInterface, ContainsInterface
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
