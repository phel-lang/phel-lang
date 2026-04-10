<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\SortedMap;

use Phel\Lang\Collections\Map\PersistentArrayMap;
use Phel\Lang\Collections\SortedMap\PersistentSortedMap;
use PhelTest\Unit\Lang\Collections\ModuloHasher;
use PhelTest\Unit\Lang\Collections\SimpleEqualizer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PersistentSortedMapTest extends TestCase
{
    public function test_empty(): void
    {
        $h = PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer());

        self::assertCount(0, $h);
        self::assertFalse($h->contains('test'));
        self::assertFalse($h->contains(null));
        self::assertNull($h->find('test'));
    }

    public function test_can_not_create_from_array_with_uneven_values(): void
    {
        $this->expectException(RuntimeException::class);
        PersistentSortedMap::fromArray(new ModuloHasher(), new SimpleEqualizer(), ['test']);
    }

    public function test_put_key_value(): void
    {
        $h = PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'test');

        self::assertCount(1, $h);
        self::assertTrue($h->contains(1));
        self::assertSame('test', $h->find(1));
    }

    public function test_put_same_key_value_twice(): void
    {
        $h = PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'test')
            ->put(1, 'test');

        self::assertCount(1, $h);
        self::assertTrue($h->contains(1));
        self::assertSame('test', $h->find(1));
    }

    public function test_put_same_key_different_value(): void
    {
        $h = PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'test')
            ->put(1, 'foo');

        self::assertCount(1, $h);
        self::assertTrue($h->contains(1));
        self::assertSame('foo', $h->find(1));
    }

    public function test_put_returns_same_instance_when_value_unchanged(): void
    {
        $h = PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'test');
        $h2 = $h->put(1, 'test');

        self::assertSame($h, $h2);
    }

    public function test_remove_existing_key(): void
    {
        $h = PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'test')
            ->remove(1);

        self::assertCount(0, $h);
        self::assertFalse($h->contains(1));
        self::assertNull($h->find(1));
    }

    public function test_remove_non_existing_key(): void
    {
        $h = PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->remove(1);

        self::assertCount(0, $h);
        self::assertFalse($h->contains(1));
        self::assertNull($h->find(1));
    }

    public function test_remove_returns_same_instance_when_key_not_found(): void
    {
        $h = PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'test');
        $h2 = $h->remove(2);

        self::assertSame($h, $h2);
    }

    public function test_remove_non_existing_key_in_child(): void
    {
        $h = PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(2, 'test')
            ->remove(1);

        self::assertCount(1, $h);
        self::assertTrue($h->contains(2));
        self::assertSame('test', $h->find(2));
        self::assertFalse($h->contains(1));
        self::assertNull($h->find(1));
    }

    public function test_merge(): void
    {
        $h1 = PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'test');

        $h2 = PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(2, 'bar');

        $merged = $h1->merge($h2);

        self::assertCount(2, $merged);
        self::assertSame('test', $merged->find(1));
        self::assertSame('bar', $merged->find(2));
    }

    public function test_equals(): void
    {
        $h1 = PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'foo')
            ->put(2, 'bar');

        $h2 = PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(2, 'bar')
            ->put(1, 'foo');

        $this->assertTrue($h1->equals($h2));
        $this->assertTrue($h2->equals($h1));
    }

    public function test_equals_different_keys(): void
    {
        $h1 = PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'foo')
            ->put(2, 'bar');

        $h2 = PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(3, 'bar')
            ->put(4, 'foo');

        $this->assertFalse($h1->equals($h2));
        $this->assertFalse($h2->equals($h1));
    }

    public function test_equals_different_length(): void
    {
        $h1 = PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'foo')
            ->put(2, 'bar')
            ->put(3, 'foobar');

        $h2 = PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(2, 'bar')
            ->put(1, 'foo');

        $this->assertFalse($h1->equals($h2));
        $this->assertFalse($h2->equals($h1));
    }

    public function test_equals_different_values(): void
    {
        $h1 = PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'foo')
            ->put(2, 'bar');

        $h2 = PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'bar')
            ->put(2, 'foo');

        $this->assertFalse($h1->equals($h2));
        $this->assertFalse($h2->equals($h1));
    }

    public function test_equals_different_type(): void
    {
        $h1 = PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'foo')
            ->put(2, 'bar');

        $this->assertFalse($h1->equals([1 => 'foo', 2 => 'bar']));
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

    public function test_iteration_on_empty(): void
    {
        $h = PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer());

        $result = [];
        foreach ($h as $k => $v) {
            $result[$k] = $v;
        }

        $this->assertSame([], $result);
    }

    public function test_hash_on_empty_map(): void
    {
        $h = PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer());

        $this->assertSame(1, $h->hash());
    }

    public function test_hash_on_single_entry_map(): void
    {
        $h = PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 10);

        $this->assertSame(1 + (1 ^ 10), $h->hash());
    }

    public function test_add_meta_data(): void
    {
        $meta = PersistentArrayMap::empty(new ModuloHasher(), new SimpleEqualizer());
        $h = PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->withMeta($meta);

        $this->assertEquals($meta, $h->getMeta());
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

    public function test_invoke_returns_value(): void
    {
        $h = PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'test');

        self::assertSame('test', $h(1));
        self::assertNull($h(2));
    }

    public function test_offset_get(): void
    {
        $h = PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'test');

        self::assertSame('test', $h[1]);
        self::assertNull($h[2]);
    }

    public function test_offset_exists(): void
    {
        $h = PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'test');

        self::assertArrayHasKey(1, $h);
        self::assertArrayNotHasKey(2, $h);
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
}
