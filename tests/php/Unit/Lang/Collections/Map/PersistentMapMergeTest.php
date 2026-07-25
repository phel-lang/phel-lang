<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\Map;

use Phel\Lang\Collections\Map\PersistentArrayMap;
use Phel\Lang\Collections\Map\PersistentHashMap;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\SortedMap\PersistentSortedMap;
use Phel\Lang\Keyword;
use PhelTest\Unit\Lang\Collections\ModuloHasher;
use PhelTest\Unit\Lang\Collections\SimpleEqualizer;
use PhelTest\Unit\Lang\Collections\Struct\FakeStruct;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function iterator_to_array;

/**
 * Pins `PersistentMapInterface::merge()` across every implementation.
 *
 * The four implementations reach merge through two different strategies:
 * `PersistentHashMap` and `PersistentArrayMap` bulk-build via a transient,
 * while `PersistentSortedMap` and `AbstractPersistentStruct` fold with
 * repeated `put()` (a struct cannot go transient at all — its `asTransient()`
 * throws). The strategies agree on entries, on last-write-wins and on keeping
 * the receiver's metadata; they still differ on identity for a no-op merge,
 * which is pinned below so any attempt to unify the two paths has to face it.
 */
final class PersistentMapMergeTest extends TestCase
{
    /**
     * @return iterable<string, array{PersistentMapInterface<mixed, mixed>, PersistentMapInterface<mixed, mixed>}>
     */
    public static function provideMapPairs(): iterable
    {
        yield 'hash map' => [self::emptyHashMap(), self::emptyHashMap()];
        yield 'array map' => [self::emptyArrayMap(), self::emptyArrayMap()];
        yield 'sorted map' => [self::emptySortedMap(), self::emptySortedMap()];
    }

    /**
     * @param PersistentMapInterface<mixed, mixed> $left
     * @param PersistentMapInterface<mixed, mixed> $right
     */
    #[DataProvider('provideMapPairs')]
    public function test_merge_combines_disjoint_entries(PersistentMapInterface $left, PersistentMapInterface $right): void
    {
        $merged = $left->put(1, 'a')->merge($right->put(2, 'b'));

        self::assertCount(2, $merged);
        self::assertSame('a', $merged->find(1));
        self::assertSame('b', $merged->find(2));
    }

    /**
     * @param PersistentMapInterface<mixed, mixed> $left
     * @param PersistentMapInterface<mixed, mixed> $right
     */
    #[DataProvider('provideMapPairs')]
    public function test_merge_lets_the_right_hand_value_win(PersistentMapInterface $left, PersistentMapInterface $right): void
    {
        $merged = $left->put(1, 'left')->put(2, 'keep')->merge($right->put(1, 'right'));

        self::assertCount(2, $merged);
        self::assertSame('right', $merged->find(1));
        self::assertSame('keep', $merged->find(2));
    }

    /**
     * @param PersistentMapInterface<mixed, mixed> $left
     * @param PersistentMapInterface<mixed, mixed> $right
     */
    #[DataProvider('provideMapPairs')]
    public function test_merge_leaves_both_operands_untouched(PersistentMapInterface $left, PersistentMapInterface $right): void
    {
        $a = $left->put(1, 'a');
        $b = $right->put(2, 'b');

        $a->merge($b);

        self::assertCount(1, $a);
        self::assertNull($a->find(2));
        self::assertCount(1, $b);
        self::assertNull($b->find(1));
    }

    /**
     * @param PersistentMapInterface<mixed, mixed> $left
     * @param PersistentMapInterface<mixed, mixed> $right
     */
    #[DataProvider('provideMapPairs')]
    public function test_merge_keeps_the_receiver_implementation(PersistentMapInterface $left, PersistentMapInterface $right): void
    {
        $merged = $left->put(1, 'a')->merge($right->put(2, 'b'));

        self::assertInstanceOf($left::class, $merged);
    }

    /**
     * @param PersistentMapInterface<mixed, mixed> $left
     * @param PersistentMapInterface<mixed, mixed> $right
     */
    #[DataProvider('provideMapPairs')]
    public function test_merge_of_an_empty_other_keeps_every_entry(PersistentMapInterface $left, PersistentMapInterface $right): void
    {
        $merged = $left->put(1, 'a')->merge($right);

        self::assertCount(1, $merged);
        self::assertSame('a', $merged->find(1));
    }

    /**
     * The transient-backed implementations keep the receiver's metadata, the
     * same as the `put()`-folding ones: `asTransient()` carries the meta into
     * the transient and `persistent()` puts it back. Clojure's `conj`-based
     * merge preserves the first map's metadata too.
     */
    public function test_merge_keeps_meta_on_the_transient_backed_maps(): void
    {
        $meta = self::emptyArrayMap()->put(Keyword::create('tag'), 'x');

        $hash = self::emptyHashMap()->put(1, 'a')->withMeta($meta);
        $array = self::emptyArrayMap()->put(1, 'a')->withMeta($meta);

        self::assertSame($meta, $hash->merge(self::emptyHashMap()->put(2, 'b'))->getMeta());
        self::assertSame($meta, $array->merge(self::emptyArrayMap()->put(2, 'b'))->getMeta());
    }

    /**
     * An array map that overflows into a hash map mid-merge keeps the metadata
     * across the promotion as well.
     */
    public function test_merge_keeps_meta_when_the_array_map_overflows(): void
    {
        $meta = self::emptyArrayMap()->put(Keyword::create('tag'), 'x');

        $left = self::emptyArrayMap();
        for ($i = 0; $i < PersistentArrayMap::MAX_SIZE; ++$i) {
            $left = $left->put($i, $i);
        }

        $merged = $left->withMeta($meta)->merge(self::emptyArrayMap()->put(PersistentArrayMap::MAX_SIZE, 'x'));

        self::assertInstanceOf(PersistentHashMap::class, $merged);
        self::assertSame($meta, $merged->getMeta());
    }

    /**
     * The `put()`-folding implementations keep the receiver's metadata,
     * because `put()` threads `$this->meta` into every copy.
     */
    public function test_merge_keeps_meta_on_the_put_folding_maps(): void
    {
        $meta = self::emptyArrayMap()->put(Keyword::create('tag'), 'x');

        $sorted = self::emptySortedMap()->put(1, 'a')->withMeta($meta);
        $struct = FakeStruct::fromKVs(Keyword::create('a'), 1, Keyword::create('b'), 2)->withMeta($meta);

        self::assertSame($meta, $sorted->merge(self::emptySortedMap()->put(2, 'b'))->getMeta());
        self::assertSame($meta, $struct->merge(FakeStruct::fromKVs(Keyword::create('a'), 9))->getMeta());
    }

    /**
     * A no-op merge short-circuits to the receiver only on the `put()`-folding
     * path; the transient path always materialises a fresh instance.
     */
    public function test_merge_of_an_empty_other_returns_the_receiver_only_when_folding(): void
    {
        $sorted = self::emptySortedMap()->put(1, 'a');
        $hash = self::emptyHashMap()->put(1, 'a');
        $array = self::emptyArrayMap()->put(1, 'a');

        self::assertSame($sorted, $sorted->merge(self::emptySortedMap()));
        self::assertNotSame($hash, $hash->merge(self::emptyHashMap()));
        self::assertNotSame($array, $array->merge(self::emptyArrayMap()));
    }

    public function test_merge_keeps_the_sorted_order_of_the_receiver(): void
    {
        $left = self::emptySortedMap()->put(5, 'e')->put(1, 'a');
        $right = self::emptySortedMap()->put(9, 'i')->put(3, 'c');

        $merged = $left->merge($right);

        self::assertSame([1, 3, 5, 9], array_keys(iterator_to_array($merged)));
    }

    public function test_merge_keeps_the_comparator_of_the_receiver(): void
    {
        $reverse = static fn($a, $b): int => $b <=> $a;
        $left = PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer(), $reverse)
            ->put(1, 'a')
            ->put(5, 'e');
        $right = self::emptySortedMap()->put(3, 'c')->put(9, 'i');

        $merged = $left->merge($right);

        self::assertInstanceOf(PersistentSortedMap::class, $merged);
        self::assertSame($reverse, $merged->getComparator());
        self::assertSame([9, 5, 3, 1], array_keys(iterator_to_array($merged)));
    }

    /**
     * An array map that outgrows `MAX_SIZE` while merging promotes to a hash
     * map, exactly as it does on a plain `put()`.
     */
    public function test_merge_promotes_an_overflowing_array_map_to_a_hash_map(): void
    {
        $left = self::emptyArrayMap();
        for ($i = 0; $i < PersistentArrayMap::MAX_SIZE; ++$i) {
            $left = $left->put($i, $i);
        }

        $merged = $left->merge(self::emptyArrayMap()->put(PersistentArrayMap::MAX_SIZE, 'x'));

        self::assertInstanceOf(PersistentHashMap::class, $merged);
        self::assertCount(PersistentArrayMap::MAX_SIZE + 1, $merged);
        self::assertSame('x', $merged->find(PersistentArrayMap::MAX_SIZE));
    }

    /**
     * A struct has no transient at all (`asTransient()` throws), so merging one
     * must stay on the `put()`-folding path. The result is another struct, and
     * a key outside `ALLOWED_KEYS` is still rejected.
     */
    public function test_merge_on_a_struct_folds_with_put(): void
    {
        $struct = FakeStruct::fromKVs(Keyword::create('a'), 1, Keyword::create('b'), 2);

        $merged = $struct->merge(FakeStruct::fromKVs(Keyword::create('a'), 42, Keyword::create('b'), 2));

        self::assertInstanceOf(FakeStruct::class, $merged);
        self::assertSame(42, $merged->find(Keyword::create('a')));
        self::assertSame(2, $merged->find(Keyword::create('b')));
        self::assertSame(1, $struct->find(Keyword::create('a')));
    }

    /**
     * @return PersistentHashMap<mixed, mixed>
     */
    private static function emptyHashMap(): PersistentHashMap
    {
        return PersistentHashMap::empty(new ModuloHasher(), new SimpleEqualizer());
    }

    /**
     * @return PersistentArrayMap<mixed, mixed>
     */
    private static function emptyArrayMap(): PersistentArrayMap
    {
        return PersistentArrayMap::empty(new ModuloHasher(), new SimpleEqualizer());
    }

    /**
     * @return PersistentSortedMap<mixed, mixed>
     */
    private static function emptySortedMap(): PersistentSortedMap
    {
        return PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer());
    }
}
