<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\SortedMap;

use Phel\Lang\Collections\Map\PersistentArrayMap;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\SortedMap\PersistentSortedMap;
use PhelTest\Unit\Lang\Collections\Map\AbstractPersistentMapContractTestCase;
use PhelTest\Unit\Lang\Collections\ModuloHasher;
use PhelTest\Unit\Lang\Collections\SimpleEqualizer;
use RuntimeException;

/**
 * The shared map behaviour lives in the contract case; this class covers what
 * is specific to the sorted flat-array storage: key ordering, the user
 * comparator, and the structural-sharing short-circuits that binary search
 * makes possible.
 */
final class PersistentSortedMapTest extends AbstractPersistentMapContractTestCase
{
    public function test_can_not_create_from_array_with_uneven_values(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('An even number of elements must be provided to build a sorted map, got 1');
        PersistentSortedMap::fromArray(new ModuloHasher(), new SimpleEqualizer(), ['test']);
    }

    public function test_put_returns_same_instance_when_value_unchanged(): void
    {
        $h = PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'test');
        $h2 = $h->put(1, 'test');

        self::assertSame($h, $h2);
    }

    public function test_remove_returns_same_instance_when_key_not_found(): void
    {
        $h = PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'test');
        $h2 = $h->remove(2);

        self::assertSame($h, $h2);
    }

    public function test_iteration_order_is_sorted(): void
    {
        $h = PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(3, 'c')
            ->put(1, 'a')
            ->put(2, 'b');

        $keys = [];
        $values = [];
        foreach ($h as $k => $v) {
            $keys[] = $k;
            $values[] = $v;
        }

        $this->assertSame([1, 2, 3], $keys);
        $this->assertSame(['a', 'b', 'c'], $values);
    }

    public function test_iteration_order_with_custom_comparator(): void
    {
        $reverseComparator = static fn($a, $b): int => $b <=> $a;
        $h = PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer(), $reverseComparator)
            ->put(1, 'a')
            ->put(3, 'c')
            ->put(2, 'b');

        $keys = [];
        foreach ($h as $k => $v) {
            $keys[] = $k;
        }

        $this->assertSame([3, 2, 1], $keys);
    }

    public function test_from_array_maintains_sorted_order(): void
    {
        $h = PersistentSortedMap::fromArray(
            new ModuloHasher(),
            new SimpleEqualizer(),
            [3, 'c', 1, 'a', 2, 'b'],
        );

        $keys = [];
        foreach ($h as $k => $v) {
            $keys[] = $k;
        }

        $this->assertSame([1, 2, 3], $keys);
    }

    public function test_from_array_with_custom_comparator(): void
    {
        $reverseComparator = static fn($a, $b): int => $b <=> $a;
        $h = PersistentSortedMap::fromArray(
            new ModuloHasher(),
            new SimpleEqualizer(),
            [3, 'c', 1, 'a', 2, 'b'],
            $reverseComparator,
        );

        $keys = [];
        foreach ($h as $k => $v) {
            $keys[] = $k;
        }

        $this->assertSame([3, 2, 1], $keys);
    }

    public function test_with_meta_preserves_comparator(): void
    {
        $reverseComparator = static fn($a, $b): int => $b <=> $a;
        $meta = PersistentArrayMap::empty(new ModuloHasher(), new SimpleEqualizer());
        $h = PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer(), $reverseComparator)
            ->put(1, 'a')
            ->put(2, 'b')
            ->withMeta($meta);

        $h2 = $h->put(3, 'c');

        $keys = [];
        foreach ($h2 as $k => $v) {
            $keys[] = $k;
        }

        $this->assertSame([3, 2, 1], $keys);
    }

    public function test_persistent_after_put(): void
    {
        $h1 = PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'a');
        $h2 = $h1->put(2, 'b');

        self::assertCount(1, $h1);
        self::assertCount(2, $h2);
        self::assertNull($h1->find(2));
        self::assertSame('b', $h2->find(2));
    }

    public function test_transient_round_trip(): void
    {
        $h = PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer());
        $t = $h->asTransient();
        $t->put(3, 'c');
        $t->put(1, 'a');
        $t->put(2, 'b');

        $result = $t->persistent();

        $keys = [];
        foreach ($result as $k => $v) {
            $keys[] = $k;
        }

        $this->assertSame([1, 2, 3], $keys);
        $this->assertCount(3, $result);
    }

    public function test_get_comparator(): void
    {
        $comp = static fn($a, $b): int => $b <=> $a;
        $h = PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer(), $comp);

        self::assertSame($comp, $h->getComparator());
    }

    public function test_get_comparator_returns_null_for_natural_order(): void
    {
        $h = PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer());

        self::assertNull($h->getComparator());
    }

    /**
     * The default comparator treats NaN as equal to itself and ordered after
     * every number, so a NaN key stays retrievable and re-assoc updates it in
     * place instead of storing a duplicate (see #2789).
     */
    public function test_default_comparator_collapses_duplicate_nan_key(): void
    {
        $h = PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(NAN, 'a')
            ->put(NAN, 'b')
            ->put(1, 'x');

        self::assertCount(2, $h);
        self::assertTrue($h->contains(NAN));
        self::assertSame('b', $h->find(NAN));
    }

    public function test_string_keys_sorted(): void
    {
        $h = PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put('c', 3)
            ->put('a', 1)
            ->put('b', 2);

        $keys = [];
        foreach ($h as $k => $v) {
            $keys[] = $k;
        }

        $this->assertSame(['a', 'b', 'c'], $keys);
    }

    protected function emptyMap(): PersistentMapInterface
    {
        return PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer());
    }
}
