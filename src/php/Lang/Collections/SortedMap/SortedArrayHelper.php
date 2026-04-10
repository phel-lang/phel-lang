<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\SortedMap;

use Closure;
use Phel\Lang\NamedInterface;

use function count;

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

        return $comparator instanceof Closure ? $comparator : Closure::fromCallable($comparator);
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
