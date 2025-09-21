<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Vector;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use Phel\Lang\Collections\AsTransientInterface;
use Phel\Lang\ConcatInterface;
use Phel\Lang\ConsInterface;
use Phel\Lang\ContainsInterface;
use Phel\Lang\FnInterface;
use Phel\Lang\PushInterface;
use Phel\Lang\SeqInterface;
use Phel\Lang\SliceInterface;
use Phel\Lang\TypeInterface;

/**
 * @template T
 *
 * @extends SeqInterface<T, PersistentVectorInterface<T>>
 * @extends IteratorAggregate<T>
 * @extends ArrayAccess<int, T>
 * @extends ConcatInterface<PersistentVectorInterface<T>>
 * @extends PushInterface<PersistentVectorInterface<T>>
 * @extends AsTransientInterface<TransientVectorInterface>
 * @extends ContainsInterface<int>
 */
interface PersistentVectorInterface extends TypeInterface, SeqInterface, IteratorAggregate, Countable, ConsInterface, ArrayAccess, ConcatInterface, PushInterface, SliceInterface, AsTransientInterface, FnInterface, ContainsInterface
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
}
