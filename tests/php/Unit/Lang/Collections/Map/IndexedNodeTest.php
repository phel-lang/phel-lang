<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\Map;

use Phel\Lang\Collections\Map\ArrayNode;
use Phel\Lang\Collections\Map\Box;
use Phel\Lang\Collections\Map\IndexedNode;
use PhelTest\Unit\Lang\Collections\ModuloHasher;
use PhelTest\Unit\Lang\Collections\SimpleEqualizer;
use PHPUnit\Framework\TestCase;

class IndexedNodeTest extends TestCase
{
    public function test_empty(): void
    {
        $hasher = new ModuloHasher();
        $h = IndexedNode::empty($hasher, new SimpleEqualizer());
        self::assertNull($h->find(0, $hasher->hash(1), 1, null));
    }

    public function test_put_key(): void
    {
        $hasher = new ModuloHasher();
        $box = new Box(null);
        $node = IndexedNode::empty($hasher, new SimpleEqualizer())
            ->put(0, $hasher->hash(1), 1, 'test', $box);

        self::assertTrue($box->getValue());
        self::assertEquals('test', $node->find(0, $hasher->hash(1), 1, null));
    }

    public function test_split_node(): void
    {
        $hasher = new ModuloHasher();
        $node = IndexedNode::empty($hasher, new SimpleEqualizer());

        for ($i = 0; $i <= 16; $i++) {
            $node = $node->put(0, $hasher->hash($i), $i, 'test' . $i, new Box(null));
        }

        self::assertInstanceOf(ArrayNode::class, $node);
    }

    public function test_put_same_key_value_pair_twice(): void
    {
        $hasher = new ModuloHasher();
        $box = new Box(null);
        $node = IndexedNode::empty($hasher, new SimpleEqualizer())
            ->put(0, $hasher->hash(1), 1, 'test', new Box(null))
            ->put(0, $hasher->hash(1), 1, 'test', $box);

        self::assertNull($box->getValue());
        self::assertEquals('test', $node->find(0, $hasher->hash(1), 1, null));
    }

    public function test_put_same_key_with_different_value(): void
    {
        $hasher = new ModuloHasher();
        $box = new Box(null);
        $node = IndexedNode::empty($hasher, new SimpleEqualizer())
            ->put(0, $hasher->hash(1), 1, 'foo', new Box(null))
            ->put(0, $hasher->hash(1), 1, 'bar', $box);

        self::assertNull($box->getValue());
        self::assertEquals('bar', $node->find(0, $hasher->hash(1), 1, null));
    }

    public function test_put_same_hash(): void
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

    public function test_put_same_index(): void
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

    public function test_add_to_child(): void
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

    public function test_add_existing_key_value_pair_to_child(): void
    {
        $hasher = new ModuloHasher(64);
        $node = IndexedNode::empty($hasher, new SimpleEqualizer())
            ->put(0, $hasher->hash(1), 1, 'foo', new Box(null))
            ->put(0, $hasher->hash(65), 65, 'bar', new Box(null))
            ->put(0, $hasher->hash(65), 65, 'bar', new Box(null));

        self::assertEquals('foo', $node->find(0, $hasher->hash(1), 1, null));
        self::assertEquals('bar', $node->find(0, $hasher->hash(65), 65, null));
    }

    public function test_remove_all_existing_keys(): void
    {
        $hasher = new ModuloHasher();
        $node = IndexedNode::empty($hasher, new SimpleEqualizer())
            ->put(0, $hasher->hash(1), 1, 'foo', new Box(null))
            ->remove(0, $hasher->hash(1), 1);

        self::assertNull($node);
    }

    public function test_remove_existing_key(): void
    {
        $hasher = new ModuloHasher();
        $node = IndexedNode::empty($hasher, new SimpleEqualizer())
            ->put(0, $hasher->hash(1), 1, 'foo', new Box(null))
            ->put(0, $hasher->hash(2), 2, 'bar', new Box(null))
            ->remove(0, $hasher->hash(2), 2);

        self::assertEquals('foo', $node->find(0, $hasher->hash(1), 1, null));
        self::assertNull($node->find(0, $hasher->hash(2), 2, null));
    }

    public function test_remove_non_existing_key(): void
    {
        $hasher = new ModuloHasher();
        $node1 = IndexedNode::empty($hasher, new SimpleEqualizer())
            ->put(0, $hasher->hash(1), 1, 'foo', new Box(null));
        $node2 = $node1->remove(0, $hasher->hash(2), 2);

        self::assertEquals('foo', $node2->find(0, $hasher->hash(1), 1, null));
        self::assertTrue($node1 === $node2);
    }

    public function test_remove_non_existing_key_on_same_index(): void
    {
        $hasher = new ModuloHasher();
        $node1 = IndexedNode::empty($hasher, new SimpleEqualizer())
            ->put(0, $hasher->hash(1), 1, 'foo', new Box(null));
        $node2 = $node1->remove(0, $hasher->hash(33), 33);

        self::assertEquals('foo', $node2->find(0, $hasher->hash(1), 1, null));
        self::assertTrue($node1 === $node2);
    }

    public function test_remove_existing_key_on_child_node(): void
    {
        $hasher = new ModuloHasher();
        $node = IndexedNode::empty($hasher, new SimpleEqualizer())
            ->put(0, $hasher->hash(1), 1, 'foo', new Box(null))
            ->put(0, $hasher->hash(33), 33, 'bar', new Box(null))
            ->remove(0, $hasher->hash(33), 33);

        self::assertEquals('foo', $node->find(0, $hasher->hash(1), 1, null));
        self::assertNull($node->find(0, $hasher->hash(33), 33, null));
    }

    public function test_remove_all_existing_keys_with_child_nodes(): void
    {
        $hasher = new ModuloHasher();
        $node = IndexedNode::empty($hasher, new SimpleEqualizer())
            ->put(0, $hasher->hash(1), 1, 'foo', new Box(null))
            ->put(0, $hasher->hash(33), 33, 'bar', new Box(null))
            ->remove(0, $hasher->hash(33), 33)
            ->remove(0, $hasher->hash(1), 1);

        self::assertNull($node);
    }

    public function test_remove_all_existing_keys_on_child_node(): void
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

    public function test_remove_non_existing_keys_on_child_node(): void
    {
        $hasher = new ModuloHasher();
        $node1 = IndexedNode::empty($hasher, new SimpleEqualizer())
            ->put(0, $hasher->hash(1), 1, 'foo', new Box(null))
            ->put(0, $hasher->hash(33), 33, 'bar', new Box(null));
        $node2 = $node1->remove(0, $hasher->hash(65), 65);

        self::assertTrue($node1 === $node2);
    }
}
