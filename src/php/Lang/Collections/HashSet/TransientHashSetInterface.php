<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\HashSet;

use Countable;
use Phel\Lang\ContainsInterface;

/**
 * @template V
 *
 * @extends ContainsInterface<V>
 */
interface TransientHashSetInterface extends Countable, ContainsInterface
{
    /**
     * @param V $value
     *
     * @return self<V>
     */
    public function add(mixed $value): self;

    /**
     * Removes $value in place; removing an absent value is a no-op.
     *
     * Not dead code: `phel.core/disj!` calls this through PHP interop
     * (`(php/-> tcoll (remove value))` in `src/phel/core/transients.phel`),
     * dispatching on this interface, so it never appears in a PHP-symbol grep.
     * It is the transient counterpart of `PersistentHashSetInterface::remove()`,
     * which `phel.core/disj` reaches the same way. Pinned by
     * `tests/php/Unit/Lang/Collections/HashSet/TransientHashSetRemoveTest.php`.
     *
     * @param V $value
     *
     * @return self<V>
     */
    public function remove(mixed $value): self;

    /**
     * @return PersistentHashSetInterface<V>
     */
    public function persistent(): PersistentHashSetInterface;
}
