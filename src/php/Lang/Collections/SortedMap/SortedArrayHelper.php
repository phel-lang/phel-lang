<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\SortedMap;

use Closure;
use Phel\Lang\NamedInterface;

use function count;
use function is_int;

/**
 * Shared binary search and comparator logic for sorted collections.
 */
final class SortedArrayHelper
{
    private static ?Closure $defaultComparator = null;

    /**
     * Default comparator: compares NamedInterface objects (Keywords, Symbols)
     * by full name, everything else via spaceship operator.
     */
    public static function defaultCompare(mixed $a, mixed $b): int
    {
        if ($a instanceof NamedInterface && $b instanceof NamedInterface) {
            return $a->getFullName() <=> $b->getFullName();
        }

        return $a <=> $b;
    }

    /**
     * Resolves a user-provided comparator into a non-null Closure.
     * Returns the default comparator when null is given.
     */
    public static function resolveComparator(?callable $comparator): Closure
    {
        if ($comparator === null) {
            return self::$defaultComparator ??= static fn(mixed $a, mixed $b): int => self::defaultCompare($a, $b);
        }

        $closure = $comparator instanceof Closure ? $comparator : Closure::fromCallable($comparator);

        // Phel users routinely pass *predicates* such as `>` or `<` here
        // (this matches Clojure semantics), but a predicate returns
        // bool, not the -1/0/1 a comparator needs. Wrap so:
        //   - bool true     -> a comes before b (-1)
        //   - bool false    -> probe (b, a); true -> 1, otherwise 0
        //   - int / numeric -> normalize to {-1, 0, 1} via spaceship
        // Without this wrapping `(sorted-map-by > ...)` collapsed every
        // key into a single slot because `>` returned `false` for the
        // equal-or-greater inputs and binarySearch read `false === 0`
        // as "key found, replace value" (issue #1705).
        return static function (mixed $a, mixed $b) use ($closure): int {
            $result = $closure($a, $b);
            if (is_bool($result)) {
                if ($result) {
                    return -1;
                }
                return $closure($b, $a) === true ? 1 : 0;
            }

            return ((int) $result) <=> 0;
        };
    }

    /**
     * Wraps a comparator so the binary search receives an int regardless
     * of whether the user passed a spaceship-style comparator (returning
     * negative/zero/positive) or a predicate-style comparator returning
     * a boolean (such as `<` or `>`).
     */
    public static function adaptForBinarySearch(Closure $comparator): Closure
    {
        return static function (mixed $a, mixed $b) use ($comparator): int {
            $result = $comparator($a, $b);
            if (is_int($result)) {
                return $result;
            }

            if ($result) {
                return -1;
            }

            return $comparator($b, $a) ? 1 : 0;
        };
    }

    /**
     * Binary search for a key in a sorted flat [k, v, k, v, ...] array.
     *
     * @param array<int, mixed> $array      The flat sorted array
     * @param mixed             $key        The key to search for
     * @param Closure           $comparator Non-null comparator function
     *
     * @return int The array index (even) if found, or -(insertionPoint) - 1 if not found
     */
    public static function binarySearch(array $array, mixed $key, Closure $comparator): int
    {
        $low = 0;
        $high = (int) (count($array) / 2) - 1;

        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);
            $cmp = $comparator($array[$mid * 2], $key);

            if ($cmp < 0) {
                $low = $mid + 1;
            } elseif ($cmp > 0) {
                $high = $mid - 1;
            } else {
                return $mid * 2;
            }
        }

        return -(($low * 2) + 1);
    }
}
