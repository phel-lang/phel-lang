<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\Map;

use Phel\Lang\Collections\Map\Box;
use Phel\Lang\Collections\Map\HashCollisionNode;
use Phel\Lang\Collections\Map\IndexedNode;
use PhelTest\Unit\Lang\Collections\ModuloHasher;
use PhelTest\Unit\Lang\Collections\SimpleEqualizer;
use PHPUnit\Framework\TestCase;

class HashCollisionNodeTest extends TestCase
{
    public function testFindOnSingleCollisionNode(): void
    {
        $hasher = new ModuloHasher();
        $node = new HashCollisionNode($hasher, new SimpleEqualizer(), $hasher->hash(1), 1, [1, 'test']);

        $this->assertEquals('test', $node->find(0, $hasher->hash(1), 1, null));
        $this->assertEquals(null, $node->find(0, $hasher->hash(2), 2, null));
    }

    public function testFindWithMultipleEntries(): void
    {
        $hasher = new ModuloHasher(2);
        $node = new HashCollisionNode(
            $hasher,
            new SimpleEqualizer(),
            $hasher->hash(1),
            2,
            [1, 'foo', 3, 'bar']
        );

        $this->assertEquals('foo', $node->find(0, $hasher->hash(1), 1, null));
        $this->assertEquals('bar', $node->find(0, $hasher->hash(3), 3, null));
        $this->assertEquals(null, $node->find(0, $hasher->hash(2), 2, null));
    }

    public function testPutAnotherKeyWithSameHash(): void
    {
        $hasher = new ModuloHasher(2);
        $box = new Box(null);
        $node = (new HashCollisionNode($hasher, new SimpleEqualizer(), $hasher->hash(1), 1, [1, 'foo']))
            ->put(0, $hasher->hash(3), 3, 'bar', $box);

        $this->assertTrue($box->getValue());
        $this->assertEquals('foo', $node->find(0, $hasher->hash(1), 1, null));
        $this->assertEquals('bar', $node->find(0, $hasher->hash(3), 3, null));
        $this->assertEquals(null, $node->find(0, $hasher->hash(2), 2, null));
    }

    public function testUpdateExistingKey(): void
    {
        $hasher = new ModuloHasher(2);
        $box = new Box(null);
        $node = (new HashCollisionNode($hasher, new SimpleEqualizer(), $hasher->hash(1), 1, [1, 'foo']))
            ->put(0, $hasher->hash(1), 1, 'bar', $box);

        $this->assertNull($box->getValue());
        $this->assertEquals('bar', $node->find(0, $hasher->hash(1), 1, null));
        $this->assertEquals(null, $node->find(0, $hasher->hash(2), 2, null));
    }

    public function testUpdateExistingKeyWithSameValue(): void
    {
        $hasher = new ModuloHasher(2);
        $box = new Box(false);
        $node = (new HashCollisionNode($hasher, new SimpleEqualizer(), $hasher->hash(1), 1, [1, 'foo']))
            ->put(0, $hasher->hash(1), 1, 'foo', $box);

        $this->assertFalse($box->getValue());
        $this->assertEquals('foo', $node->find(0, $hasher->hash(1), 1, null));
        $this->assertEquals(null, $node->find(0, $hasher->hash(2), 2, null));
    }

    public function testPutAnotherHash(): void
    {
        $hasher = new ModuloHasher(2);
        $box = new Box(null);
        $node = (new HashCollisionNode($hasher, new SimpleEqualizer(), $hasher->hash(1), 1, [1, 'foo']))
            ->put(0, $hasher->hash(2), 2, 'bar', $box);

        $this->assertTrue($box->getValue());
        $this->assertInstanceOf(IndexedNode::class, $node);
        $this->assertEquals('foo', $node->find(0, $hasher->hash(1), 1, null));
        $this->assertEquals('bar', $node->find(0, $hasher->hash(2), 2, null));
        $this->assertEquals(null, $node->find(0, $hasher->hash(3), 3, null));
    }

    public function testRemoveOnlyInsertedKey(): void
    {
        $hasher = new ModuloHasher(2);
        $node = (new HashCollisionNode($hasher, new SimpleEqualizer(), $hasher->hash(1), 1, [1, 'foo']))
            ->remove(0, $hasher->hash(1), 1);

        $this->assertNull($node);
    }

    public function testRemoveNonExistingKey(): void
    {
        $hasher = new ModuloHasher(2);
        $node = (new HashCollisionNode($hasher, new SimpleEqualizer(), $hasher->hash(1), 1, [1, 'foo']))
            ->remove(0, $hasher->hash(2), 2);

        $this->assertEquals('foo', $node->find(0, $hasher->hash(1), 1, null));
        $this->assertNull($node->find(0, $hasher->hash(2), 2, null));
    }

    public function testRemoveOneCollisionKey(): void
    {
        $hasher = new ModuloHasher(2);
        $node = (new HashCollisionNode(
            $hasher,
            new SimpleEqualizer(),
            $hasher->hash(1),
            2,
            [1, 'foo', 3, 'bar']
        ))->remove(0, $hasher->hash(3), 3);

        $this->assertEquals('foo', $node->find(0, $hasher->hash(1), 1, null));
        $this->assertNull($node->find(0, $hasher->hash(3), 3, null));
    }
}
