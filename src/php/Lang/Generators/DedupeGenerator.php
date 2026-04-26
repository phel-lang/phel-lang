<?php

declare(strict_types=1);

namespace Phel\Lang\Generators;

use Generator;
use Phel\Lang\TypeFactory;

use function count;
use function in_array;
use function is_object;

/**
 * Value-equality and sentinel-removal generators: distinct, dedupe, and compact.
 *
 * Each operation produces a lazy sequence that has been cleansed of duplicates or
 * unwanted sentinel values. Equality is delegated to the runtime TypeFactory's
 * hasher/equalizer so persistent Phel collections compare correctly.
 */
final class DedupeGenerator
{
    /**
     * Returns unique elements from an iterable, preserving first occurrence order.
     * Uses hash-based equality checking for efficient deduplication.
     *
     * Examples:
     *   distinct([1, 2, 1, 3, 2, 4])  // => [1, 2, 3, 4]
     *   distinct('abracadabra')       // => ['a', 'b', 'r', 'c', 'd']
     *
     * @template T
     *
     * @param iterable<T>|string $iterable
     *
     * @return Generator<int, T>
     */
    public static function distinct(mixed $iterable): Generator
    {
        $typeFactory = TypeFactory::getInstance();
        $hasher = $typeFactory->getHasher();
        $equalizer = $typeFactory->getEqualizer();
        $seen = [];

        foreach (SequenceGenerator::toIterable($iterable) as $value) {
            $hash = $hasher->hash($value);

            // Check if we've seen an equal value with this hash
            $found = false;
            if (isset($seen[$hash])) {
                foreach ($seen[$hash] as $seenValue) {
                    if ($equalizer->equals($value, $seenValue)) {
                        $found = true;
                        break;
                    }
                }
            }

            if (!$found) {
                $seen[$hash][] = $value;
                yield $value;
            }
        }
    }

    /**
     * Removes consecutive duplicate elements from an iterable.
     * Unlike distinct(), only removes duplicates that are adjacent to each other.
     *
     * Examples:
     *   dedupe([1, 1, 2, 2, 2, 3, 1, 1])  // => [1, 2, 3, 1]
     *   dedupe('aabbcc')                  // => ['a', 'b', 'c']
     *   dedupe([1, 2, 3, 4])              // => [1, 2, 3, 4] (no consecutive dupes)
     *
     * @template T
     *
     * @param iterable<T>|string $iterable
     *
     * @return Generator<int, T>
     */
    public static function dedupe(mixed $iterable): Generator
    {
        $equalizer = TypeFactory::getInstance()->getEqualizer();
        $first = true;
        $prev = null;

        foreach (SequenceGenerator::toIterable($iterable) as $value) {
            if ($first || !$equalizer->equals($value, $prev)) {
                yield $value;
                $prev = $value;
                $first = false;
            }
        }
    }

    /**
     * Removes null values (and optionally other specified values) from an iterable.
     *
     * This provides an explicit, composable way to filter out unwanted sentinel values
     * from a collection. Inspired by loophp/collection's Compact operation.
     *
     * Uses strict comparison (===) to preserve type safety. For example:
     *   - null and '' (empty string) are distinct
     *   - 0 (int) and '0' (string) are distinct
     *   - false and 0 are distinct
     *
     * Examples:
     *   compact([1, null, 2, null, 3])
     *   // => [1, 2, 3]
     *
     *   compact([1, null, 2, false, 3], null, false)
     *   // => [1, 2, 3]
     *
     *   compact([null, '', 0, false], null)
     *   // => ['', 0, false]  - only null removed, not similar values
     *
     * Unlike filter(), compact() is specifically designed for removing unwanted
     * sentinel values, making intent clearer in code.
     *
     * @template T
     *
     * @param iterable<T>|string $iterable  The input sequence
     * @param mixed              ...$values Values to remove (default: just null)
     *
     * @return Generator<int, T>
     */
    public static function compact(mixed $iterable, mixed ...$values): Generator
    {
        $valuesToRemove = $values === [] ? [null] : $values;

        if (count($valuesToRemove) === 1) {
            yield from self::compactSingleValue($iterable, $valuesToRemove[0]);
            return;
        }

        $lookups = self::prepareCompactLookups($valuesToRemove);

        foreach (SequenceGenerator::toIterable($iterable) as $item) {
            if (!self::shouldRemoveItem($item, $lookups['scalarLookup'], $lookups['objects'])) {
                yield $item;
            }
        }
    }

    /**
     * Optimized path for removing a single value from an iterable.
     *
     * @template T
     *
     * @param iterable<T>|string $iterable
     *
     * @return Generator<int, T>
     */
    private static function compactSingleValue(mixed $iterable, mixed $valueToRemove): Generator
    {
        foreach (SequenceGenerator::toIterable($iterable) as $item) {
            if ($item !== $valueToRemove) {
                yield $item;
            }
        }
    }

    /**
     * Prepares lookup structures for efficient value removal.
     * Separates scalars (hashable) from objects (identity comparison).
     *
     * @param array<mixed> $valuesToRemove
     *
     * @return array{scalarLookup: array<string, true>, objects: array<object>}
     */
    private static function prepareCompactLookups(array $valuesToRemove): array
    {
        $scalarLookup = [];
        $objects = [];

        foreach ($valuesToRemove as $value) {
            if (is_object($value)) {
                $objects[] = $value;
            } else {
                $scalarLookup[var_export($value, true)] = true;
            }
        }

        return ['scalarLookup' => $scalarLookup, 'objects' => $objects];
    }

    /**
     * Determines if an item should be removed based on lookup structures.
     *
     * @param array<string, true> $scalarLookup
     * @param array<object>       $objectsToRemove
     */
    private static function shouldRemoveItem(mixed $item, array $scalarLookup, array $objectsToRemove): bool
    {
        if (is_object($item)) {
            return in_array($item, $objectsToRemove, true);
        }

        return isset($scalarLookup[var_export($item, true)]);
    }

}
