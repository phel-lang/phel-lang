<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\HashMap;

use Phel\Lang\Collections\HashMap\ArrayNode;
use Phel\Lang\Collections\HashMap\Box;
use Phel\Lang\Collections\HashMap\IndexedNode;
use PHPUnit\Framework\TestCase;

class IndexedNodeTest extends TestCase
{
    public function testEmpty(): void
    {
        $hasher = new ModuloHasher();
        $h = IndexedNode::empty($hasher, new SimpleEqualizer());
        self::assertNull($h->find(0, $hasher->hash(1), 1, null));
    }

    public function testPutKey(): void
    {
        $hasher = new ModuloHasher();
        $box = new Box(null);
        $node = IndexedNode::empty($hasher, new SimpleEqualizer())
            ->put(0, $hasher->hash(1), 1, 'test', $box);

        self::assertTrue($box->getValue());
        self::assertEquals('test', $node->find(0, $hasher->hash(1), 1, null));
    }

    public function testSplitNode(): void
    {
        $hasher = new ModuloHasher();
        $node = IndexedNode::empty($hasher, new SimpleEqualizer());

        for ($i = 0; $i <= 16; $i++) {
            $node = $node->put(0, $hasher->hash($i), $i, 'test' . $i, new Box(null));
        }

        self::assertInstanceOf(ArrayNode::class, $node);
    }

    public function testPutSameKeyValuePairTwice(): void
    {
        $hasher = new ModuloHasher();
        $box = new Box(null);
        $node = IndexedNode::empty($hasher, new SimpleEqualizer())
            ->put(0, $hasher->hash(1), 1, 'test', new Box(null))
            ->put(0, $hasher->hash(1), 1, 'test', $box);

        self::assertNull($box->getValue());
        self::assertEquals('test', $node->find(0, $hasher->hash(1), 1, null));
    }

    public function testPutSameKeyWithDifferentValue(): void
    {
        $hasher = new ModuloHasher();
        $box = new Box(null);
        $node = IndexedNode::empty($hasher, new SimpleEqualizer())
            ->put(0, $hasher->hash(1), 1, 'foo', new Box(null))
            ->put(0, $hasher->hash(1), 1, 'bar', $box);

        self::assertNull($box->getValue());
        self::assertEquals('bar', $node->find(0, $hasher->hash(1), 1, null));
    }

    public function testPutSameHash(): void
    {
        $hasher = new ModuloHasher(2);
        $box = new Box(null);
        $node = IndexedNode::empty($hasher, new SimpleEqualizer())
            ->put(0, $hasher->hash(1), 1, 'foo', new Box(null))
            ->put(0, $hasher->hash(3), 3, 'bar', $box);

        self::assertTrue($box->getValue());
        self::assertEquals('foo', $node->find(0, $hasher->hash(1), 1, null));
        self::assertEquals('bar', $node->find(0, $hasher->hash(3), 3, null));
    }

    public function testPutSameIndex(): void
    {
        $hasher = new ModuloHasher();
        $box = new Box(null);
        $node = IndexedNode::empty($hasher, new SimpleEqualizer())
            ->put(0, $hasher->hash(1), 1, 'foo', new Box(null))
            ->put(0, $hasher->hash(33), 33, 'bar', $box);

        self::assertTrue($box->getValue());
        self::assertEquals('foo', $node->find(0, $hasher->hash(1), 1, null));
        self::assertEquals('bar', $node->find(0, $hasher->hash(33), 33, null));
    }

    public function testAddToChild(): void
    {
        $hasher = new ModuloHasher(64);
        $node = IndexedNode::empty($hasher, new SimpleEqualizer())
            ->put(0, $hasher->hash(1), 1, 'foo', new Box(null))
            ->put(0, $hasher->hash(65), 65, 'bar', new Box(null))
            ->put(0, $hasher->hash(33), 33, 'foobar', new Box(null));

        self::assertEquals('foo', $node->find(0, $hasher->hash(1), 1, null));
        self::assertEquals('bar', $node->find(0, $hasher->hash(65), 65, null));
        self::assertEquals('foobar', $node->find(0, $hasher->hash(33), 33, null));
    }

    public function testAddExistingKeyValuePairToChild(): void
    {
        $hasher = new ModuloHasher(64);
        $node = IndexedNode::empty($hasher, new SimpleEqualizer())
            ->put(0, $hasher->hash(1), 1, 'foo', new Box(null))
            ->put(0, $hasher->hash(65), 65, 'bar', new Box(null))
            ->put(0, $hasher->hash(65), 65, 'bar', new Box(null));

        self::assertEquals('foo', $node->find(0, $hasher->hash(1), 1, null));
        self::assertEquals('bar', $node->find(0, $hasher->hash(65), 65, null));
    }

    public function testRemoveAllExistingKeys(): void
    {
        $hasher = new ModuloHasher();
        $node = IndexedNode::empty($hasher, new SimpleEqualizer())
            ->put(0, $hasher->hash(1), 1, 'foo', new Box(null))
            ->remove(0, $hasher->hash(1), 1);

        self::assertNull($node);
    }

    public function testRemoveExistingKey(): void
    {
        $hasher = new ModuloHasher();
        $node = IndexedNode::empty($hasher, new SimpleEqualizer())
            ->put(0, $hasher->hash(1), 1, 'foo', new Box(null))
            ->put(0, $hasher->hash(2), 2, 'bar', new Box(null))
            ->remove(0, $hasher->hash(2), 2);

        self::assertEquals('foo', $node->find(0, $hasher->hash(1), 1, null));
        self::assertNull($node->find(0, $hasher->hash(2), 2, null));
    }

    public function testRemoveNonExistingKey(): void
    {
        $hasher = new ModuloHasher();
        $node1 = IndexedNode::empty($hasher, new SimpleEqualizer())
            ->put(0, $hasher->hash(1), 1, 'foo', new Box(null));
        $node2 = $node1->remove(0, $hasher->hash(2), 2);

        self::assertEquals('foo', $node2->find(0, $hasher->hash(1), 1, null));
        self::assertTrue($node1 === $node2);
    }

    public function testRemoveNonExistingKeyOnSameIndex(): void
    {
        $hasher = new ModuloHasher();
        $node1 = IndexedNode::empty($hasher, new SimpleEqualizer())
            ->put(0, $hasher->hash(1), 1, 'foo', new Box(null));
        $node2 = $node1->remove(0, $hasher->hash(33), 33);

        self::assertEquals('foo', $node2->find(0, $hasher->hash(1), 1, null));
        self::assertTrue($node1 === $node2);
    }

    public function testRemoveExistingKeyOnChildNode(): void
    {
        $hasher = new ModuloHasher();
        $node = IndexedNode::empty($hasher, new SimpleEqualizer())
            ->put(0, $hasher->hash(1), 1, 'foo', new Box(null))
            ->put(0, $hasher->hash(33), 33, 'bar', new Box(null))
            ->remove(0, $hasher->hash(33), 33);

        self::assertEquals('foo', $node->find(0, $hasher->hash(1), 1, null));
        self::assertNull($node->find(0, $hasher->hash(33), 33, null));
    }

    public function testRemoveAllExistingKeysWithChildNodes(): void
    {
        $hasher = new ModuloHasher();
        $node = IndexedNode::empty($hasher, new SimpleEqualizer())
            ->put(0, $hasher->hash(1), 1, 'foo', new Box(null))
            ->put(0, $hasher->hash(33), 33, 'bar', new Box(null))
            ->remove(0, $hasher->hash(33), 33)
            ->remove(0, $hasher->hash(1), 1);

        self::assertNull($node);
    }

    public function testRemoveAllExistingKeysOnChildNode(): void
    {
        $hasher = new ModuloHasher();
        $node = IndexedNode::empty($hasher, new SimpleEqualizer())
            ->put(0, $hasher->hash(10), 10, 'keep', new Box(null))
            ->put(0, $hasher->hash(1), 1, 'foo', new Box(null))
            ->put(0, $hasher->hash(33), 33, 'bar', new Box(null))
            ->remove(0, $hasher->hash(33), 33)
            ->remove(0, $hasher->hash(1), 1);

        self::assertNull($node->find(0, $hasher->hash(1), 1, null));
        self::assertNull($node->find(0, $hasher->hash(33), 33, null));
    }

    public function testRemoveNonExistingKeysOnChildNode(): void
    {
        $hasher = new ModuloHasher();
        $node1 = IndexedNode::empty($hasher, new SimpleEqualizer())
            ->put(0, $hasher->hash(1), 1, 'foo', new Box(null))
            ->put(0, $hasher->hash(33), 33, 'bar', new Box(null));
        $node2 = $node1->remove(0, $hasher->hash(65), 65);

        self::assertTrue($node1 === $node2);
    }
}
