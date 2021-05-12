<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\Map;

use Phel\Lang\Collections\Map\PersistentArrayMap;
use Phel\Lang\Collections\Map\TransientArrayMap;
use Phel\Lang\Collections\Map\TransientHashMap;
use PhelTest\Unit\Lang\Collections\ModuloHasher;
use PhelTest\Unit\Lang\Collections\SimpleEqualizer;
use PHPUnit\Framework\TestCase;

class TransientArrayMapTest extends TestCase
{
    public function testEmpty(): void
    {
        $h = TransientArrayMap::empty(new ModuloHasher(), new SimpleEqualizer());

        self::assertEquals(0, $h->count());
        self::assertFalse(isset($h['test']));
        self::assertFalse(isset($h[null]));
        self::assertNull($h->find('test'));
    }

    public function testAddNullKey(): void
    {
        $h = TransientArrayMap::empty(new ModuloHasher(), new SimpleEqualizer());
        $h2 = $h->put(null, 'test');

        self::assertEquals('test', $h[null]);
        self::assertEquals(1, $h->count());
        self::assertTrue($h->contains(null));
        self::assertEquals('test', $h2[null]);
        self::assertEquals(1, $h2->count());
        self::assertTrue($h2->contains(null));
    }

    public function testPutKeyValue(): void
    {
        $h = TransientArrayMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'test');

        self::assertEquals(1, $h->count());
        self::assertTrue($h->contains(1));
        self::assertEquals('test', $h->find(1));
    }

    public function testPutSameKeyValueTwice(): void
    {
        $h = TransientArrayMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'test')
            ->put(1, 'test');

        self::assertEquals(1, $h->count());
        self::assertTrue($h->contains(1));
        self::assertEquals('test', $h->find(1));
    }

    public function testPutSameKeyDifferentValueTwice(): void
    {
        $h = TransientArrayMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'test')
            ->put(1, 'foo');

        self::assertEquals(1, $h->count());
        self::assertTrue($h->contains(1));
        self::assertEquals('foo', $h->find(1));
    }

    public function testPutNullTwice(): void
    {
        $h = TransientArrayMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(null, 'test')
            ->put(null, 'test');

        self::assertEquals(1, $h->count());
        self::assertTrue($h->contains(null));
        self::assertEquals('test', $h->find(null));
    }

    public function testConvertToTransientHashMap(): void
    {
        $h = TransientArrayMap::empty(new ModuloHasher(), new SimpleEqualizer());
        for ($i = 0; $i < PersistentArrayMap::MAX_SIZE + 1; $i++) {
            $h = $h->put($i, 'foo');
        }

        $this->assertInstanceOf(TransientHashMap::class, $h);
    }

    public function testRemoveExistingNullKey(): void
    {
        $h = TransientArrayMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(null, 'test')
            ->remove(null);

        self::assertEquals(0, $h->count());
        self::assertFalse($h->contains(null));
        self::assertNull($h->find(null));
    }

    public function testRemoveNonExistingNullKey(): void
    {
        $h = TransientArrayMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->remove(null);

        self::assertEquals(0, $h->count());
        self::assertFalse($h->contains(null));
        self::assertNull($h->find(null));
    }

    public function testRemoveNonExistingKey(): void
    {
        $h = TransientArrayMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->remove(1);

        self::assertEquals(0, $h->count());
        self::assertFalse($h->contains(1));
        self::assertNull($h->find(1));
    }

    public function testRemoveNonExistingKeyInChild(): void
    {
        $h = TransientArrayMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(2, 'test')
            ->remove(1);

        self::assertEquals(1, $h->count());
        self::assertTrue($h->contains(2));
        self::assertEquals('test', $h->find(2));
        self::assertFalse($h->contains(1));
        self::assertNull($h->find(1));
    }

    public function testRemoveExistingKey(): void
    {
        $h = TransientArrayMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'test')
            ->remove(1);

        self::assertEquals(0, $h->count());
        self::assertFalse($h->contains(1));
        self::assertNull($h->find(1));
    }
}
