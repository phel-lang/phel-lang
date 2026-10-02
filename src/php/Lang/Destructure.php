<?php

declare(strict_types=1);

namespace Phel\Lang;

use ArrayAccess;
use InvalidArgumentException;
use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;
use Phel\Lang\Collections\LazySeq\Cons;
use Phel\Lang\Collections\LazySeq\LazySeqInterface;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Shared\Printer\Printer;

use function array_pop;
use function count;
use function is_array;
use function is_string;
use function sprintf;

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
 * The map half: a map pattern over anything but a map reads the value the
 * way Clojure does before its inline `php/aget` lookups run (#3479).
 *
 * Do NOT rename: `VectorBindingDeconstructor` and `MapBindingDeconstructor`
 * bake this FQN into generated PHP, and cached `.phel` artifacts keep calling
 * it by that name.
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

    /**
     * What `:as` binds in a map pattern: a seq read as keyword arguments,
     * `(apply hash-map s)`, with a trailing map merged over the pairs as
     * Clojure 1.11 does. A seq of one element is that element. Every other
     * value is itself.
     */
    public static function kwargs(mixed $value): mixed
    {
        if (!self::isSeq($value)) {
            return $value;
        }

        $items = [];
        foreach ($value as $item) {
            $items[] = $item;
        }

        if (count($items) === 1) {
            return $items[0];
        }

        $tail = count($items) % 2 === 1 ? array_pop($items) : null;
        if ($tail !== null && !$tail instanceof PersistentMapInterface) {
            throw new InvalidArgumentException(sprintf('No value supplied for key: %s', Printer::readable()->print($tail)));
        }

        $map = TypeFactory::getInstance()->persistentMapFromKVs()->asTransient();
        for ($i = 0, $n = count($items); $i < $n; $i += 2) {
            $map = $map->put($items[$i], $items[$i + 1]);
        }

        foreach ($tail ?? [] as $key => $entry) {
            $map = $map->put($key, $entry);
        }

        return $map->persistent();
    }

    /**
     * What the lookups of a map pattern index into. They compile to
     * `($m[$key] ?? null)`, so the value must answer that the way `get`
     * would: a set answers with its member, a seq with its keyword
     * arguments, and a value `get` reads nothing from with nil.
     */
    public static function lookupSource(mixed $value): mixed
    {
        $value = self::kwargs($value);

        if ($value instanceof PersistentHashSetInterface) {
            $map = TypeFactory::getInstance()->persistentMapFromKVs()->asTransient();
            foreach ($value as $member) {
                $map = $map->put($member, $member);
            }

            return $map->persistent();
        }

        if ($value === null || $value instanceof ArrayAccess || is_array($value) || is_string($value)) {
            return $value;
        }

        return null;
    }

    private static function isSeq(mixed $value): bool
    {
        return $value instanceof PersistentListInterface
            || $value instanceof LazySeqInterface
            || $value instanceof Cons;
    }
}
