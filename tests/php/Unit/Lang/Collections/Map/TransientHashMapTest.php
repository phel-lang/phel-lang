<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\Map;

use Phel\Lang\Collections\Map\TransientHashMap;
use PhelTest\Unit\Lang\Collections\ModuloHasher;
use PhelTest\Unit\Lang\Collections\SimpleEqualizer;
use PHPUnit\Framework\TestCase;

final class TransientHashMapTest extends TestCase
{
    public function test_empty(): void
    {
        $h = TransientHashMap::empty(new ModuloHasher(), new SimpleEqualizer());

        self::assertCount(0, $h);
        self::assertNull($h[null]);
        self::assertNull($h['test']);
        self::assertNull($h->find('test'));
    }

    public function test_add_null_key(): void
    {
        $h = TransientHashMap::empty(new ModuloHasher(), new SimpleEqualizer());
        $h2 = $h->put(null, 'test');

        self::assertSame('test', $h[null]);
        self::assertCount(1, $h);
        self::assertTrue($h->contains(null));
        self::assertSame('test', $h2[null]);
        self::assertCount(1, $h2);
        self::assertTrue($h2->contains(null));
    }

    public function test_put_key_value(): void
    {
        $h = TransientHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'test');

        self::assertCount(1, $h);
        self::assertTrue($h->contains(1));
        self::assertSame('test', $h->find(1));
    }

    public function test_put_same_key_value_twice(): void
    {
        $h = TransientHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'test')
            ->put(1, 'test');

        self::assertCount(1, $h);
        self::assertTrue($h->contains(1));
        self::assertSame('test', $h->find(1));
    }

    public function test_put_null_twice(): void
    {
        $h = TransientHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(null, 'test')
            ->put(null, 'test');

        self::assertCount(1, $h);
        self::assertTrue($h->contains(null));
        self::assertSame('test', $h->find(null));
    }

    public function test_remove_existing_null_key(): void
    {
        $h = TransientHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(null, 'test')
            ->remove(null);

        self::assertCount(0, $h);
        self::assertFalse($h->contains(null));
        self::assertNull($h->find(null));
    }

    public function test_remove_non_existing_null_key(): void
    {
        $h = TransientHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->remove(null);

        self::assertCount(0, $h);
        self::assertFalse($h->contains(null));
        self::assertNull($h->find(null));
    }

    public function test_remove_non_existing_key(): void
    {
        $h = TransientHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->remove(1);

        self::assertCount(0, $h);
        self::assertFalse($h->contains(1));
        self::assertNull($h->find(1));
    }

    public function test_remove_non_existing_key_in_child(): void
    {
        $h = TransientHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
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
        $h = TransientHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'test')
            ->remove(1);

        self::assertCount(0, $h);
        self::assertFalse($h->contains(1));
        self::assertNull($h->find(1));
    }

    public function test_remove_absent_key_on_large_map_is_noop(): void
    {
        $size = 100;
        $h = TransientHashMap::empty(new ModuloHasher(), new SimpleEqualizer());
        for ($i = 0; $i < $size; ++$i) {
            $h = $h->put($i, $i);
        }

        $result = $h->remove($size + 1);

        // No-op removal leaves count and entries untouched (O(1) identity check).
        self::assertSame($h, $result);
        self::assertCount($size, $result);
        self::assertTrue($result->contains(0));
        self::assertTrue($result->contains($size - 1));
    }

    public function test_remove_present_key_on_large_map(): void
    {
        $size = 100;
        $h = TransientHashMap::empty(new ModuloHasher(), new SimpleEqualizer());
        for ($i = 0; $i < $size; ++$i) {
            $h = $h->put($i, $i);
        }

        $h->remove(42);

        self::assertCount($size - 1, $h);
        self::assertFalse($h->contains(42));
        self::assertNull($h->find(42));
        self::assertTrue($h->contains(41));
        self::assertTrue($h->contains(43));
    }
}
