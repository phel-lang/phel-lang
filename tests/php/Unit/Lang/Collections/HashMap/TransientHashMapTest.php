<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\HashMap;

use Phel\Lang\Collections\HashMap\TransientHashMap;
use PhelTest\Unit\Lang\Collections\ModuloHasher;
use PhelTest\Unit\Lang\Collections\SimpleEqualizer;
use PHPUnit\Framework\TestCase;

class TransientHashMapTest extends TestCase
{
    public function testEmpty(): void
    {
        $h = TransientHashMap::empty(new ModuloHasher(), new SimpleEqualizer());

        self::assertEquals(0, $h->count());
        self::assertFalse(isset($h['test']));
        self::assertFalse(isset($h[null]));
        self::assertNull($h->find('test'));
    }

    public function testAddNullKey(): void
    {
        $h = TransientHashMap::empty(new ModuloHasher(), new SimpleEqualizer());
        $h2 = $h->put(null, 'test');

        self::assertEquals('test', $h[null]);
        self::assertEquals(1, $h->count());
        self::assertTrue($h->containsKey(null));
        self::assertEquals('test', $h2[null]);
        self::assertEquals(1, $h2->count());
        self::assertTrue($h2->containsKey(null));
    }

    public function testPutKeyValue(): void
    {
        $h = TransientHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'test');

        self::assertEquals(1, $h->count());
        self::assertTrue($h->containsKey(1));
        self::assertEquals('test', $h->find(1));
    }

    public function testPutSameKeyValueTwice(): void
    {
        $h = TransientHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'test')
            ->put(1, 'test');

        self::assertEquals(1, $h->count());
        self::assertTrue($h->containsKey(1));
        self::assertEquals('test', $h->find(1));
    }

    public function testPutNullTwice(): void
    {
        $h = TransientHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(null, 'test')
            ->put(null, 'test');

        self::assertEquals(1, $h->count());
        self::assertTrue($h->containsKey(null));
        self::assertEquals('test', $h->find(null));
    }

    public function testRemoveExistingNullKey(): void
    {
        $h = TransientHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(null, 'test')
            ->remove(null);

        self::assertEquals(0, $h->count());
        self::assertFalse($h->containsKey(null));
        self::assertNull($h->find(null));
    }

    public function testRemoveNonExistingNullKey(): void
    {
        $h = TransientHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->remove(null);

        self::assertEquals(0, $h->count());
        self::assertFalse($h->containsKey(null));
        self::assertNull($h->find(null));
    }

    public function testRemoveNonExistingKey(): void
    {
        $h = TransientHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->remove(1);

        self::assertEquals(0, $h->count());
        self::assertFalse($h->containsKey(1));
        self::assertNull($h->find(1));
    }

    public function testRemoveNonExistingKeyInChild(): void
    {
        $h = TransientHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(2, 'test')
            ->remove(1);

        self::assertEquals(1, $h->count());
        self::assertTrue($h->containsKey(2));
        self::assertEquals('test', $h->find(2));
        self::assertFalse($h->containsKey(1));
        self::assertNull($h->find(1));
    }

    public function testRemoveExistingKey(): void
    {
        $h = TransientHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'test')
            ->remove(1);

        self::assertEquals(0, $h->count());
        self::assertFalse($h->containsKey(1));
        self::assertNull($h->find(1));
    }
}
