<?php

declare(strict_types=1);

namespace Phel\Lang;

use Phel\Lang\Collections\Vector\PersistentVectorInterface;

/**
 * Runtime half of the indexed fast path for sequential destructuring (#3356).
 *
 * A `[a b & r]` pattern tests once whether the value is a vector, reads its
 * positions by index inline in the generated PHP, and walks every other value
 * with `first`/`next` as it always has. The `& r` tail of a vector must be
 * exactly what `next` applied once per position returns: a `SubVector`
 * sharing the source (and its metadata), or `nil` once exhausted. One
 * position is a plain `cdr()` in the generated code; this covers two or more.
 *
 * Do NOT rename: `VectorBindingDeconstructor` bakes this FQN into generated
 * PHP, and cached `.phel` artifacts keep calling it by that name.
 */
final class Destructure
{
    private function __construct() {}

    /**
     * What `(next (next … v))` with `$n` calls answers for a vector: `next`
     * on a vector is its `cdr()`, and `nil` stays `nil`.
     *
     * @param PersistentVectorInterface<mixed> $vector
     */
    public static function nthNext(PersistentVectorInterface $vector, int $n): mixed
    {
        $current = $vector;
        for ($i = 0; $i < $n && $current !== null; ++$i) {
            $current = $current->cdr();
        }

        return $current;
    }
}
