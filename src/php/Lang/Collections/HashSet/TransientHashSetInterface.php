<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\HashSet;

use Countable;
use Phel\Lang\Collections\AsTransientInterface;
use Phel\Lang\Collections\Map\TransientMapInterface;
use Phel\Lang\ContainsInterface;

/**
 * @template V
 *
 * @extends AsTransientInterface<TransientMapInterface>
 * @extends ContainsInterface<V>
 */
interface TransientHashSetInterface extends Countable, ContainsInterface
{
    /**
     * @param V $value
     */
    public function add($value): self;

    /**
     * @param V $value
     */
    public function remove($value): self;

    public function persistent(): PersistentHashSetInterface;
}
