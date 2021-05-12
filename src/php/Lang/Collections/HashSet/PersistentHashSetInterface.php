<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\HashSet;

use Countable;
use Phel\Lang\Collections\AsTransientInterface;
use Phel\Lang\ConcatInterface;
use Phel\Lang\ContainsInterface;
use Phel\Lang\FnInterface;

/**
 * @template V
 *
 * @extends AsTransientInterface<TransientHashSetInterface>
 * @extends ContainsInterface<V>
 */
interface PersistentHashSetInterface extends Countable, AsTransientInterface, FnInterface, ConcatInterface, ContainsInterface
{
    /**
     * @param V $value
     */
    public function add($value): PersistentHashSetInterface;

    /**
     * @param V $value
     */
    public function remove($value): PersistentHashSetInterface;

    public function toPhpArray(): array;
}
