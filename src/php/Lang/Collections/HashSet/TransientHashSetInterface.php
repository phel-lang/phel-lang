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
     * Removes $value in place; removing an absent value is a no-op.
     *
     * Called from Phel through PHP interop by `phel.core/disj!`
     * (`(php/-> tcoll (remove value))` in `src/phel/core/transients.phel`), so it
     * has no PHP-side call site to grep for. Do not remove as unused.
     *
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
