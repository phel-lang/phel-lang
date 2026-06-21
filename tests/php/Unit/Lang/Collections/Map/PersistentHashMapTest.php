<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\Map;

use Phel\Lang\Collections\Map\PersistentHashMap;
use PhelTest\Unit\Lang\Collections\ModuloHasher;
use PhelTest\Unit\Lang\Collections\SimpleEqualizer;
use PHPUnit\Framework\TestCase;

final class PersistentHashMapTest extends TestCase
{
    public function test_empty(): void
    {
        $h = PersistentHashMap::empty(new ModuloHasher(), new SimpleEqualizer());

        self::assertCount(0, $h);
        self::assertFalse($h->contains('test'));
        self::assertFalse($h->contains(null));
        self::assertNull($h->find('test'));
    }

    public function test_add_null_key(): void
    {
        $h = PersistentHashMap::empty(new ModuloHasher(), new SimpleEqualizer());
        $h2 = $h->put(null, 'test');

        self::assertNull($h->find(null));
        self::assertCount(0, $h);
        self::assertFalse($h->contains(null));
        self::assertSame('test', $h2->find(null));
        self::assertCount(1, $h2);
        self::assertTrue($h2->contains(null));
    }

    public function test_put_key_value(): void
    {
        $h = PersistentHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'test');

        self::assertCount(1, $h);
        self::assertTrue($h->contains(1));
        self::assertSame('test', $h->find(1));
    }

    public function test_put_same_key_value_twice(): void
    {
        $h = PersistentHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'test')
            ->put(1, 'test');

        self::assertCount(1, $h);
        self::assertTrue($h->contains(1));
        self::assertSame('test', $h->find(1));
    }

    public function test_put_null_twice(): void
    {
        $h = PersistentHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(null, 'test')
            ->put(null, 'test');

        self::assertCount(1, $h);
        self::assertTrue($h->contains(null));
        self::assertSame('test', $h->find(null));
    }

    public function test_merge(): void
    {
        $h1 = PersistentHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'test');

        $h2 = PersistentHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(2, 'bar');

        $expected = PersistentHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'test')
            ->put(2, 'bar');

        $this->assertEquals($expected, $h1->merge($h2));
    }

    public function test_remove_existing_null_key(): void
    {
        $h = PersistentHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(null, 'test')
            ->remove(null);

        self::assertCount(0, $h);
        self::assertFalse($h->contains(null));
        self::assertNull($h->find(null));
    }

    public function test_remove_non_existing_null_key(): void
    {
        $h = PersistentHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->remove(null);

        self::assertCount(0, $h);
        self::assertFalse($h->contains(null));
        self::assertNull($h->find(null));
    }

    public function test_remove_non_existing_key(): void
    {
        $h = PersistentHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->remove(1);

        self::assertCount(0, $h);
        self::assertFalse($h->contains(1));
        self::assertNull($h->find(1));
    }

    public function test_remove_non_existing_key_in_child(): void
    {
        $h = PersistentHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(2, 'test')
            ->remove(1);

        self::assertCount(1, $h);
        self::assertTrue($h->contains(2));
        self::assertSame('test', $h->find(2));
        self::assertFalse($h->contains(1));
        self::assertNull($h->find(1));
    }

    public function test_remove_existing_key(): void
    {
        $h = PersistentHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'test')
            ->remove(1);

        self::assertCount(0, $h);
        self::assertFalse($h->contains(1));
        self::assertNull($h->find(1));
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

    public function test_equals(): void
    {
        $h1 = PersistentHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'foo')
            ->put(2, 'bar');

        $h2 = PersistentHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(2, 'bar')
            ->put(1, 'foo');

        $this->assertTrue($h1->equals($h2));
        $this->assertTrue($h2->equals($h1));
    }

    public function test_equals_different_keys(): void
    {
        $h1 = PersistentHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'foo')
            ->put(2, 'bar');

        $h2 = PersistentHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(3, 'bar')
            ->put(4, 'foo');

        $this->assertFalse($h1->equals($h2));
        $this->assertFalse($h2->equals($h1));
    }

    public function test_equals_different_length(): void
    {
        $h1 = PersistentHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'foo')
            ->put(2, 'bar')
            ->put(3, 'foobar');

        $h2 = PersistentHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(2, 'bar')
            ->put(1, 'foo');

        $this->assertFalse($h1->equals($h2));
        $this->assertFalse($h2->equals($h1));
    }

    public function test_equals_different_values(): void
    {
        $h1 = PersistentHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'foo')
            ->put(2, 'bar');

        $h2 = PersistentHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'bar')
            ->put(2, 'foo');

        $this->assertFalse($h1->equals($h2));
        $this->assertFalse($h2->equals($h1));
    }

    public function test_equals_different_type(): void
    {
        $h1 = PersistentHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'foo')
            ->put(2, 'bar');

        $this->assertFalse($h1->equals([1 => 'foo', 2 => 'bar']));
    }

    public function test_iteratable(): void
    {
        $h = PersistentHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'foo')
            ->put(2, 'bar')
            ->put(3, 'foobar');

        $result = [];
        foreach ($h as $k => $v) {
            $result[$k] = $v;
        }

        $this->assertSame([1 => 'foo', 2 => 'bar', 3 => 'foobar'], $result);
    }

    public function test_iteratable_on_empty(): void
    {
        $h = PersistentHashMap::empty(new ModuloHasher(), new SimpleEqualizer());

        $result = [];
        foreach ($h as $k => $v) {
            $result[$k] = $v;
        }

        $this->assertSame([], $result);
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

    public function test_hash_on_empty_map(): void
    {
        $h = PersistentHashMap::empty(new ModuloHasher(), new SimpleEqualizer());

        $this->assertSame(1, $h->hash());
    }

    public function test_hash_on_single_entryy_map(): void
    {
        $h = PersistentHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 10);

        $this->assertSame(1 + (1 ^ 10), $h->hash());
    }
}
