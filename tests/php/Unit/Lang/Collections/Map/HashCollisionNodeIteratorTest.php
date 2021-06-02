<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\Map;

use Phel\Lang\Collections\Map\HashCollisionNode;
use PhelTest\Unit\Lang\Collections\ModuloHasher;
use PhelTest\Unit\Lang\Collections\SimpleEqualizer;
use PHPUnit\Framework\TestCase;

class HashCollisionNodeIteratorTest extends TestCase
{
    public function test_iterate_on_empty_node(): void
    {
        $node = new HashCollisionNode(new ModuloHasher(), new SimpleEqualizer(), 1, 0, []);

        $result = [];
        foreach ($node as $k => $v) {
            $result[$k] = $v;
        }

        $this->assertEmpty($result);
    }

    public function test_iterate_on_single_entry_node(): void
    {
        $node = new HashCollisionNode(new ModuloHasher(), new SimpleEqualizer(), 1, 1, [1, 'foo']);

        $result = [];
        foreach ($node as $k => $v) {
            $result[$k] = $v;
        }

        $this->assertEquals([1 => 'foo'], $result);
    }

    public function test_iterate_on_two_entry_node(): void
    {
        $node = new HashCollisionNode(new ModuloHasher(), new SimpleEqualizer(), 1, 2, [1, 'foo', 3, 'bar']);

        $result = [];
        foreach ($node as $k => $v) {
            $result[$k] = $v;
        }

        $this->assertEquals([1 => 'foo', 3 => 'bar'], $result);
    }
}
