<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\Map;

use Phel\Lang\Collections\Map\Box;
use Phel\Lang\Collections\Map\IndexedNode;
use PhelTest\Unit\Lang\Collections\ModuloHasher;
use PhelTest\Unit\Lang\Collections\SimpleEqualizer;
use PHPUnit\Framework\TestCase;

class IndexedNodeIteratorTest extends TestCase
{
    public function test_iterate_on_empty_node(): void
    {
        $node = IndexedNode::empty(new ModuloHasher(), new SimpleEqualizer());

        $result = [];
        foreach ($node as $k => $v) {
            $result[$k] = $v;
        }

        $this->assertEmpty($result);
    }

    public function test_iterate_on_single_entry_node(): void
    {
        $node = IndexedNode::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(0, 1, 1, 'foo', new Box(false));

        $result = [];
        foreach ($node as $k => $v) {
            $result[$k] = $v;
        }

        $this->assertEquals([1 => 'foo'], $result);
    }

    public function test_iterate_on_two_entry_node(): void
    {
        $node = IndexedNode::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(0, 1, 1, 'foo', new Box(false))
            ->put(0, 2, 2, 'bar', new Box(false));

        $result = [];
        foreach ($node as $k => $v) {
            $result[$k] = $v;
        }

        $this->assertEquals([1 => 'foo', 2 => 'bar'], $result);
    }

    public function test_iterate_on_three_entry_node_with_hash_collision(): void
    {
        $node = IndexedNode::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(0, 1, 1, 'foo', new Box(false))
            ->put(0, 2, 2, 'bar', new Box(false))
            ->put(0, 1, 3, 'foobar', new Box(false));

        $result = [];
        foreach ($node as $k => $v) {
            $result[$k] = $v;
        }

        $this->assertEquals([1 => 'foo', 2 => 'bar', 3 => 'foobar'], $result);
    }

    public function test_iterate_on_multiple_child_nodes(): void
    {
        $node = IndexedNode::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(0, 1, 1, 'foo', new Box(false))
            ->put(0, 2, 2, 'bar', new Box(false))
            ->put(0, 1, 3, 'foobar', new Box(false))
            ->put(0, 2, 4, 'barbar', new Box(false));

        $result = [];
        foreach ($node as $k => $v) {
            $result[$k] = $v;
        }

        $this->assertEquals([1 => 'foo', 2 => 'bar', 3 => 'foobar', 4 => 'barbar'], $result);
    }
}
