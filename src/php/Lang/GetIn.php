<?php

declare(strict_types=1);

namespace Phel\Lang;

use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;
use Phel\Lang\Collections\LazySeq\LazySeqInterface;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;

use function is_array;
use function is_int;
use function is_string;

/**
 * The walk a `(get-in ds [k1 k2 …])` with a literal path compiles to (#3320).
 *
 * The runtime `phel.core/get-in` needs the path as a persistent vector and
 * steps through it with `first`/`next`, testing every level with three Phel
 * predicates before a `get`. With the keys written at the call site the
 * emitter hands them over as a PHP array, and this answers what that loop
 * answers: `$notFound` for a nil or non-traversable level and for a nil
 * result, the target itself for an empty path.
 *
 * Maps and vectors are read here, with the exact reads `phel.core/get` makes
 * for them. Any other traversable level (a list, set, lazy seq, PHP array or
 * string, or a vector indexed by a `BigInt`) is handed to `phel.core/get`.
 *
 * Do NOT rename: `GetInCallEmitter` bakes this FQN into generated PHP, and
 * cached `.phel` artifacts keep calling it by that name.
 */
final class GetIn
{
    private function __construct() {}

    /**
     * @param array<int, mixed> $keys
     */
    public static function path(mixed $ds, array $keys, mixed $notFound = null): mixed
    {
        if ($keys === []) {
            return $ds;
        }

        $res = $ds;
        foreach ($keys as $key) {
            // `offsetExists` then `offsetGet` is what `php/aget`'s
            // `($coll[$k] ?? null)` does, spelled out so the key stays mixed.
            if ($res instanceof PersistentMapInterface) {
                $res = $res->offsetExists($key) ? $res->offsetGet($key) : null;
            } elseif ($res instanceof PersistentVectorInterface && !$key instanceof BigInt) {
                $res = is_int($key) && $res->offsetExists($key) ? $res->offsetGet($key) : null;
            } elseif (self::isTraversable($res)) {
                /** @var callable(mixed, mixed): mixed $get */
                $get = Registry::readRoot('phel.core', 'get');
                $res = $get($res, $key);
            } else {
                return $notFound;
            }
        }

        return $res ?? $notFound;
    }

    /**
     * `get-in-traversable?`: `coll?`, a PHP array or a string. A transient is
     * none of these, so the walk stops at one even though `get` would read it.
     * Maps never reach here; a vector does only with a `BigInt` key.
     */
    private static function isTraversable(mixed $x): bool
    {
        return $x instanceof PersistentVectorInterface
            || $x instanceof PersistentListInterface
            || $x instanceof PersistentHashSetInterface
            || $x instanceof LazySeqInterface
            || is_array($x)
            || is_string($x);
    }
}
