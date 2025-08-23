<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\Map;

use Phel\Lang\Collections\Map\ArrayNode;
use Phel\Lang\Collections\Map\Box;
use PhelTest\Unit\Lang\Collections\ModuloHasher;
use PhelTest\Unit\Lang\Collections\SimpleEqualizer;
use PHPUnit\Framework\TestCase;

final class ArrayNodeIteratorTest extends TestCase
{
    public function test_iterate_on_empty_node(): void
    {
        $node = ArrayNode::empty(new ModuloHasher(), new SimpleEqualizer());

        $result = [];
        foreach ($node as $k => $v) {
            $result[$k] = $v;
        }

        $this->assertEmpty($result);
    }

    public function test_iterate_on_single_entry_node(): void
    {
        $node = ArrayNode::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(0, 1, 1, 'foo', new Box(false));

        $result = [];
        foreach ($node as $k => $v) {
            $result[$k] = $v;
        }

        $this->assertSame([1 => 'foo'], $result);
    }

    public function test_iterate_on_two_entry_node(): void
    {
        $node = ArrayNode::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(0, 1, 1, 'foo', new Box(false))
            ->put(0, 2, 2, 'bar', new Box(false));

        $result = [];
        foreach ($node as $k => $v) {
            $result[$k] = $v;
        }

        $this->assertSame([1 => 'foo', 2 => 'bar'], $result);
    }

    public function test_iterate_on_node_with_removed_entry(): void
    {
        $node = ArrayNode::empty(new ModuloHasher(), new SimpleEqualizer());
        for ($i = 1; $i <= 9; ++$i) {
            $node = $node->put(0, $i, $i, 'value' . $i, new Box(false));
        }

        $node = $node->remove(0, 5, 5);

        $result = [];
        foreach ($node as $k => $v) {
            $result[$k] = $v;
        }

        $this->assertSame([
            1 => 'value1',
            2 => 'value2',
            3 => 'value3',
            4 => 'value4',
            6 => 'value6',
            7 => 'value7',
            8 => 'value8',
            9 => 'value9',
        ], $result);
    }
}
