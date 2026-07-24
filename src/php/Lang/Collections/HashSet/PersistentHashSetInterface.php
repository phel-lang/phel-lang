<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\HashSet;

use Countable;
use IteratorAggregate;
use Phel\Lang\Collections\AsTransientInterface;
use Phel\Lang\ConcatInterface;
use Phel\Lang\ContainsInterface;
use Phel\Lang\FnInterface;
use Phel\Lang\TypeInterface;

/**
 * @template TValue
 *
 * @extends AsTransientInterface<TransientHashSetInterface<TValue>>
 * @extends IteratorAggregate<int, TValue>
 * @extends ContainsInterface<TValue>
 * @extends ConcatInterface<PersistentHashSetInterface<TValue>>
 */
interface PersistentHashSetInterface extends TypeInterface, Countable, IteratorAggregate, AsTransientInterface, FnInterface, ConcatInterface, ContainsInterface
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
     * @return array<int, TValue>
     */
    public function toPhpArray(): array;
}
