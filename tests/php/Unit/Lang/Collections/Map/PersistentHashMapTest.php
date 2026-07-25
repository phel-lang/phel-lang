<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\Map;

use Phel\Lang\Collections\Map\PersistentHashMap;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Equalizer;
use Phel\Lang\Hasher;
use PhelTest\Unit\Lang\Collections\ModuloHasher;
use PhelTest\Unit\Lang\Collections\SimpleEqualizer;
use RuntimeException;

/**
 * The shared map behaviour lives in the contract case; this class covers what
 * is specific to the hash-array-mapped-trie storage: insertion-order
 * iteration, the node promotion that only a large map reaches, and the hash
 * accumulator's overflow guard.
 */
final class PersistentHashMapTest extends AbstractPersistentMapContractTestCase
{
    public function test_can_not_create_from_array_with_uneven_values(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('An even number of elements must be provided to build a map, got 3');
        PersistentHashMap::fromArray(new ModuloHasher(), new SimpleEqualizer(), ['a', 1, 'b']);
    }

    public function test_iteration_follows_insertion_order(): void
    {
        $h = PersistentHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(3, 'foobar')
            ->put(1, 'foo')
            ->put(2, 'bar');

        $result = [];
        foreach ($h as $k => $v) {
            $result[] = $k;
        }

        $this->assertSame([3, 1, 2], $result);
    }

    public function test_remove_absent_key_on_large_map_returns_same_instance(): void
    {
        $size = 100;
        $h = PersistentHashMap::empty(new ModuloHasher(), new SimpleEqualizer());
        for ($i = 0; $i < $size; ++$i) {
            $h = $h->put($i, $i);
        }

        $result = $h->remove($size + 1);

        // No-op removal returns the original map by identity (O(1) identity check).
        self::assertSame($h, $result);
        self::assertTrue($h->equals($result));
        self::assertCount($size, $result);
    }

    public function test_remove_present_key_on_large_map(): void
    {
        $size = 100;
        $h = PersistentHashMap::empty(new ModuloHasher(), new SimpleEqualizer());
        for ($i = 0; $i < $size; ++$i) {
            $h = $h->put($i, $i);
        }

        $result = $h->remove(42);

        self::assertNotSame($h, $result);
        self::assertCount($size - 1, $result);
        self::assertFalse($result->contains(42));
        self::assertNull($result->find(42));
        // Original map is untouched (persistent).
        self::assertCount($size, $h);
        self::assertTrue($h->contains(42));
        self::assertSame(42, $h->find(42));
    }

    public function test_iteratable_on_large_map_yields_every_entry(): void
    {
        // A map this large forces IndexedNode promotion to ArrayNode
        // (>= 16 children in a single node), so iteration walks the
        // ArrayNode -> ArrayNodeIterator path under test.
        $size = 2000;
        $h = PersistentHashMap::empty(new ModuloHasher(), new SimpleEqualizer());
        $expected = [];
        for ($i = 0; $i < $size; ++$i) {
            $h = $h->put($i, $i * 2);
            $expected[$i] = $i * 2;
        }

        $collected = [];
        foreach ($h as $k => $v) {
            $collected[$k] = $v;
        }

        ksort($collected);

        self::assertCount($size, $collected);
        self::assertSame($expected, $collected);
    }

    public function test_iteratable_on_large_map_after_removals_drops_null_slots(): void
    {
        // After dissoc-ing keys, ArrayNode child slots become null. The
        // iterator must still yield exactly the remaining entries and
        // never surface a null slot (array_filter in ArrayNodeIterator).
        $size = 2000;
        $h = PersistentHashMap::empty(new ModuloHasher(), new SimpleEqualizer());
        $expected = [];
        for ($i = 0; $i < $size; ++$i) {
            $h = $h->put($i, $i * 2);
            $expected[$i] = $i * 2;
        }

        foreach ([0, 5, 500, 1234, 1500, 1999] as $removed) {
            $h = $h->remove($removed);
            unset($expected[$removed]);
        }

        $collected = [];
        foreach ($h as $k => $v) {
            $collected[$k] = $v;
        }

        ksort($collected);

        self::assertCount($size - 6, $collected);
        self::assertSame($expected, $collected);
    }

    public function test_merge_and_equals_on_large_maps(): void
    {
        // Behavioural guard over the iterator path: merge walks the
        // source map's entries, and equals walks both. Two large maps
        // built in different insertion orders must merge and compare
        // identically.
        $size = 1500;
        $a = PersistentHashMap::empty(new ModuloHasher(), new SimpleEqualizer());
        $b = PersistentHashMap::empty(new ModuloHasher(), new SimpleEqualizer());
        for ($i = 0; $i < $size; ++$i) {
            $a = $a->put($i, $i);
        }

        for ($i = $size - 1; $i >= 0; --$i) {
            $b = $b->put($i, $i);
        }

        self::assertTrue($a->equals($b));
        self::assertTrue($b->equals($a));

        $merged = $a->merge($b);
        self::assertCount($size, $merged);
        self::assertTrue($merged->equals($a));

        $collected = [];
        foreach ($merged as $k => $v) {
            $collected[$k] = $v;
        }

        ksort($collected);
        self::assertCount($size, $collected);
        for ($i = 0; $i < $size; ++$i) {
            self::assertSame($i, $collected[$i]);
        }
    }

    /**
     * Regression for the int->float overflow in the running hash sum: a
     * large map must still return a stable int hash instead of throwing a
     * TypeError on the `?int $hashCache` assignment.
     */
    public function test_hash_large_map_returns_stable_int(): void
    {
        $map = PersistentHashMap::empty(new Hasher(), new Equalizer());
        for ($i = 0; $i < 1000; ++$i) {
            $map = $map->put($i, $i * 7);
        }

        $hash = $map->hash();

        $this->assertIsInt($hash);
        $this->assertSame($hash, $map->hash());
    }

    protected function emptyMap(): PersistentMapInterface
    {
        return PersistentHashMap::empty(new ModuloHasher(), new SimpleEqualizer());
    }
}
