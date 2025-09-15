<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\HashSet;

use Countable;
use Phel\Lang\Collections\AsTransientInterface;
use Phel\Lang\ConcatInterface;
use Phel\Lang\ContainsInterface;
use Phel\Lang\FnInterface;
use Phel\Lang\TypeInterface;

/**
 * @template V
 *
 * @extends AsTransientInterface<TransientHashSetInterface>
 * @extends ContainsInterface<V>
 */
interface PersistentHashSetInterface extends TypeInterface, Countable, AsTransientInterface, FnInterface, ConcatInterface, ContainsInterface
{
    /**
     * @param V $value
     */
    public function add(mixed $value): self;

    /**
     * @param V $value
     */
    public function remove(mixed $value): self;

    public function toPhpArray(): array;
}
