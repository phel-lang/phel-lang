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

        self::assertSame(0, $h->count());
        self::assertNull($h[null]);
        self::assertNull($h['test']);
        self::assertNull($h->find('test'));
    }

    public function test_add_null_key(): void
    {
        $h = TransientHashMap::empty(new ModuloHasher(), new SimpleEqualizer());
        $h2 = $h->put(null, 'test');

        self::assertSame('test', $h[null]);
        self::assertSame(1, $h->count());
        self::assertTrue($h->contains(null));
        self::assertSame('test', $h2[null]);
        self::assertSame(1, $h2->count());
        self::assertTrue($h2->contains(null));
    }

    public function test_put_key_value(): void
    {
        $h = TransientHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'test');

        self::assertSame(1, $h->count());
        self::assertTrue($h->contains(1));
        self::assertSame('test', $h->find(1));
    }

    public function test_put_same_key_value_twice(): void
    {
        $h = TransientHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'test')
            ->put(1, 'test');

        self::assertSame(1, $h->count());
        self::assertTrue($h->contains(1));
        self::assertSame('test', $h->find(1));
    }

    public function test_put_null_twice(): void
    {
        $h = TransientHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(null, 'test')
            ->put(null, 'test');

        self::assertSame(1, $h->count());
        self::assertTrue($h->contains(null));
        self::assertSame('test', $h->find(null));
    }

    public function test_remove_existing_null_key(): void
    {
        $h = TransientHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(null, 'test')
            ->remove(null);

        self::assertSame(0, $h->count());
        self::assertFalse($h->contains(null));
        self::assertNull($h->find(null));
    }

    public function test_remove_non_existing_null_key(): void
    {
        $h = TransientHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->remove(null);

        self::assertSame(0, $h->count());
        self::assertFalse($h->contains(null));
        self::assertNull($h->find(null));
    }

    public function test_remove_non_existing_key(): void
    {
        $h = TransientHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->remove(1);

        self::assertSame(0, $h->count());
        self::assertFalse($h->contains(1));
        self::assertNull($h->find(1));
    }

    public function test_remove_non_existing_key_in_child(): void
    {
        $h = TransientHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(2, 'test')
            ->remove(1);

        self::assertSame(1, $h->count());
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

        self::assertSame(0, $h->count());
        self::assertFalse($h->contains(1));
        self::assertNull($h->find(1));
    }
}
