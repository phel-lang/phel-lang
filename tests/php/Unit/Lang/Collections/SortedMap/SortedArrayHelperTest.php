<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\SortedMap;

use Closure;
use Phel\Lang\Collections\SortedMap\SortedArrayHelper;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class SortedArrayHelperTest extends TestCase
{
    // ---- defaultCompare ----

    public function test_default_compare_integers(): void
    {
        self::assertSame(-1, SortedArrayHelper::defaultCompare(1, 2));
        self::assertSame(0, SortedArrayHelper::defaultCompare(2, 2));
        self::assertSame(1, SortedArrayHelper::defaultCompare(3, 2));
    }

    public function test_default_compare_strings(): void
    {
        self::assertSame(-1, SortedArrayHelper::defaultCompare('a', 'b'));
        self::assertSame(0, SortedArrayHelper::defaultCompare('b', 'b'));
        self::assertSame(1, SortedArrayHelper::defaultCompare('c', 'b'));
    }

    public function test_default_compare_keywords_by_name(): void
    {
        $a = Keyword::create('alpha');
        $b = Keyword::create('beta');
        $c = Keyword::create('gamma');

        self::assertLessThan(0, SortedArrayHelper::defaultCompare($a, $b));
        self::assertSame(0, SortedArrayHelper::defaultCompare($a, $a));
        self::assertGreaterThan(0, SortedArrayHelper::defaultCompare($c, $b));
    }

    public function test_default_compare_namespaced_keywords(): void
    {
        $a = Keyword::create('name', 'a');
        $b = Keyword::create('name', 'b');

        self::assertLessThan(0, SortedArrayHelper::defaultCompare($a, $b));
        self::assertGreaterThan(0, SortedArrayHelper::defaultCompare($b, $a));
    }

    public function test_default_compare_symbols_by_name(): void
    {
        $a = Symbol::create('alpha');
        $b = Symbol::create('beta');

        self::assertLessThan(0, SortedArrayHelper::defaultCompare($a, $b));
        self::assertGreaterThan(0, SortedArrayHelper::defaultCompare($b, $a));
    }

    // ---- resolveComparator ----

    public function test_resolve_null_returns_default_comparator(): void
    {
        $comp = SortedArrayHelper::resolveComparator(null);

        self::assertInstanceOf(Closure::class, $comp);
        self::assertSame(-1, $comp(1, 2));
    }

    public function test_resolve_null_returns_singleton(): void
    {
        $comp1 = SortedArrayHelper::resolveComparator(null);
        $comp2 = SortedArrayHelper::resolveComparator(null);

        self::assertSame($comp1, $comp2);
    }

    public function test_resolve_closure_returns_same_closure(): void
    {
        $custom = static fn(int $a, int $b): int => $b <=> $a;
        $result = SortedArrayHelper::resolveComparator($custom);

        self::assertSame($custom, $result);
    }

    public function test_resolve_callable_converts_to_closure(): void
    {
        $result = SortedArrayHelper::resolveComparator(strcmp(...));

        self::assertInstanceOf(Closure::class, $result);
    }

    // ---- binarySearch ----

    public function test_search_empty_array(): void
    {
        $comp = SortedArrayHelper::resolveComparator(null);

        self::assertSame(-1, SortedArrayHelper::binarySearch([], 1, $comp));
    }

    public function test_search_finds_existing_key(): void
    {
        $comp = SortedArrayHelper::resolveComparator(null);
        $array = [1, 'a', 2, 'b', 3, 'c'];

        self::assertSame(0, SortedArrayHelper::binarySearch($array, 1, $comp));
        self::assertSame(2, SortedArrayHelper::binarySearch($array, 2, $comp));
        self::assertSame(4, SortedArrayHelper::binarySearch($array, 3, $comp));
    }

    public function test_search_returns_negative_for_missing_key(): void
    {
        $comp = SortedArrayHelper::resolveComparator(null);
        $array = [1, 'a', 3, 'c', 5, 'e'];

        // Missing key 2: insertion point is index 2 → returns -(2+1) = -3
        self::assertSame(-3, SortedArrayHelper::binarySearch($array, 2, $comp));
        // Missing key 4: insertion point is index 4 → returns -(4+1) = -5
        self::assertSame(-5, SortedArrayHelper::binarySearch($array, 4, $comp));
    }

    public function test_search_insertion_point_at_start(): void
    {
        $comp = SortedArrayHelper::resolveComparator(null);
        $array = [2, 'b', 3, 'c'];

        // Key 1 inserts at index 0 → returns -(0+1) = -1
        self::assertSame(-1, SortedArrayHelper::binarySearch($array, 1, $comp));
    }

    public function test_search_insertion_point_at_end(): void
    {
        $comp = SortedArrayHelper::resolveComparator(null);
        $array = [1, 'a', 2, 'b'];

        // Key 3 inserts at index 4 → returns -(4+1) = -5
        self::assertSame(-5, SortedArrayHelper::binarySearch($array, 3, $comp));
    }

    public function test_search_with_custom_comparator(): void
    {
        $reverse = static fn(int $a, int $b): int => $b <=> $a;
        $array = [3, 'c', 2, 'b', 1, 'a']; // reverse sorted

        self::assertSame(0, SortedArrayHelper::binarySearch($array, 3, $reverse));
        self::assertSame(2, SortedArrayHelper::binarySearch($array, 2, $reverse));
        self::assertSame(4, SortedArrayHelper::binarySearch($array, 1, $reverse));
    }

    public function test_search_single_element(): void
    {
        $comp = SortedArrayHelper::resolveComparator(null);
        $array = [5, 'e'];

        self::assertSame(0, SortedArrayHelper::binarySearch($array, 5, $comp));
        self::assertSame(-1, SortedArrayHelper::binarySearch($array, 3, $comp));
        self::assertSame(-3, SortedArrayHelper::binarySearch($array, 7, $comp));
    }

    public function test_search_with_keywords(): void
    {
        $comp = SortedArrayHelper::resolveComparator(null);
        $a = Keyword::create('a');
        $b = Keyword::create('b');
        $c = Keyword::create('c');
        $array = [$a, 1, $b, 2, $c, 3];

        self::assertSame(0, SortedArrayHelper::binarySearch($array, $a, $comp));
        self::assertSame(2, SortedArrayHelper::binarySearch($array, $b, $comp));
        self::assertSame(4, SortedArrayHelper::binarySearch($array, $c, $comp));
    }
}
