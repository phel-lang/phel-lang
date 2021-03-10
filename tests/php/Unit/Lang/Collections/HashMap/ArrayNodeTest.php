<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\HashMap;

use Phel\Lang\Collections\HashMap\ArrayNode;
use Phel\Lang\Collections\HashMap\Box;
use Phel\Lang\Collections\HashMap\IndexedNode;
use PHPUnit\Framework\TestCase;

class ArrayNodeTest extends TestCase
{
    public function testEmpty(): void
    {
        $hasher = new ModuloHasher();
        $node = ArrayNode::empty($hasher, new SimpleEqualizer());
        self::assertEquals(0, $node->count());
        self::assertNull($node->find(0, $hasher->hash(1), 1, null));
    }

    public function testPutNonExistingKey(): void
    {
        $hasher = new ModuloHasher();
        $node = ArrayNode::empty($hasher, new SimpleEqualizer())
            ->put(0, $hasher->hash(1), 1, 'test', new Box(null));

        self::assertInstanceOf(ArrayNode::class, $node);
        self::assertEquals('test', $node->find(0, $hasher->hash(1), 1, null));
    }

    public function testPutSameKeyTwice(): void
    {
        $hasher = new ModuloHasher();
        $node = ArrayNode::empty($hasher, new SimpleEqualizer())
            ->put(0, $hasher->hash(1), 1, 'foo', new Box(null))
            ->put(0, $hasher->hash(1), 1, 'bar', new Box(null));

        self::assertInstanceOf(ArrayNode::class, $node);
        self::assertEquals('bar', $node->find(0, $hasher->hash(1), 1, null));
    }

    public function testPutSameKeyValueTwice(): void
    {
        $hasher = new ModuloHasher();
        $node = ArrayNode::empty($hasher, new SimpleEqualizer())
            ->put(0, $hasher->hash(1), 1, 'foo', new Box(null))
            ->put(0, $hasher->hash(1), 1, 'foo', new Box(null));

        self::assertInstanceOf(ArrayNode::class, $node);
        self::assertEquals('foo', $node->find(0, $hasher->hash(1), 1, null));
    }

    public function testRemoveNonExistingKey(): void
    {
        $hasher = new ModuloHasher();
        $node = ArrayNode::empty($hasher, new SimpleEqualizer())
            ->remove(0, $hasher->hash(1), 1);

        self::assertInstanceOf(ArrayNode::class, $node);
    }

    public function testPackToIndexNode(): void
    {
        $hasher = new ModuloHasher();
        /** @var IndexedNode $node */
        $node = ArrayNode::empty($hasher, new SimpleEqualizer())
            ->put(0, $hasher->hash(1), 1, 'foo', new Box(null))
            ->put(0, $hasher->hash(33), 33, 'bar', new Box(null))
            ->put(0, $hasher->hash(2), 2, 'foobar', new Box(null))
            ->remove(0, $hasher->hash(1), 1)
            ->remove(0, $hasher->hash(33), 33);

        self::assertInstanceOf(IndexedNode::class, $node);
        self::assertEquals('foobar', $node->find(0, $hasher->hash(2), 2, null));
        self::assertNull($node->find(0, $hasher->hash(1), 1, null));
        self::assertNull($node->find(0, $hasher->hash(33), 33, null));
    }

    public function testRemoveKeyNoPacking(): void
    {
        $hasher = new ModuloHasher();
        $node = ArrayNode::empty($hasher, new SimpleEqualizer())
            ->put(0, $hasher->hash(1), 1, 'foo1', new Box(null))
            ->put(0, $hasher->hash(2), 2, 'foo2', new Box(null))
            ->put(0, $hasher->hash(3), 3, 'foo3', new Box(null))
            ->put(0, $hasher->hash(4), 4, 'foo4', new Box(null))
            ->put(0, $hasher->hash(5), 5, 'foo5', new Box(null))
            ->put(0, $hasher->hash(6), 6, 'foo6', new Box(null))
            ->put(0, $hasher->hash(7), 7, 'foo7', new Box(null))
            ->put(0, $hasher->hash(8), 8, 'foo8', new Box(null))
            ->remove(0, $hasher->hash(8), 8);

        self::assertInstanceOf(ArrayNode::class, $node);
        self::assertNull($node->find(0, $hasher->hash(8), 8, null));
    }
}
