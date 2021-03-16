<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\Vector;

use Phel\Lang\Collections\LinkedList\EmptyList;
use Phel\Lang\Collections\LinkedList\PersistentList;
use PhelTest\Unit\Lang\Collections\ModuloHasher;
use PhelTest\Unit\Lang\Collections\SimpleEqualizer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EmptyListTest extends TestCase
{
    public function testPrependOnEmptyList(): void
    {
        $list = (new EmptyList(new ModuloHasher(), new SimpleEqualizer(), null))->prepend('foo');

        $this->assertTrue($list instanceof PersistentList);
        $this->assertEquals(1, $list->count());
        $this->assertEquals('foo', $list->get(0));
    }

    public function testCanNotPopOnEmtpyList(): void
    {
        $this->expectException(RuntimeException::class);

        $list = new EmptyList(new ModuloHasher(), new SimpleEqualizer(), null);
        $list->pop();
    }

    public function testCount(): void
    {
        $list = new EmptyList(new ModuloHasher(), new SimpleEqualizer(), null);
        $this->assertEquals(0, $list->count());
    }

    public function testCanGetOnEmtpyList(): void
    {
        $this->expectException(RuntimeException::class);

        $list = new EmptyList(new ModuloHasher(), new SimpleEqualizer(), null);
        $list->get(0);
    }

    public function testEqualsDifferentType(): void
    {
        $list = new EmptyList(new ModuloHasher(), new SimpleEqualizer(), null);
        $this->assertFalse($list->equals([]));
    }

    public function testEqualsSameType(): void
    {
        $list = new EmptyList(new ModuloHasher(), new SimpleEqualizer(), null);
        $this->assertTrue($list->equals(new EmptyList(new ModuloHasher(), new SimpleEqualizer(), null)));
    }

    public function testHash(): void
    {
        $list = new EmptyList(new ModuloHasher(), new SimpleEqualizer(), null);
        $this->assertEquals(1, $list->hash());
    }

    public function testIterator(): void
    {
        $list = new EmptyList(new ModuloHasher(), new SimpleEqualizer(), null);

        $result = [];
        foreach ($list as $index => $value) {
            $result[$index] = $value;
        }
        $this->assertEquals([], $result);
    }

    public function testFirst(): void
    {
        $list = new EmptyList(new ModuloHasher(), new SimpleEqualizer(), null);
        $this->assertNull($list->first());
    }

    public function testRest(): void
    {
        $list = new EmptyList(new ModuloHasher(), new SimpleEqualizer(), null);
        $this->assertEquals($list, $list->rest());
    }

    public function testCdr(): void
    {
        $list = new EmptyList(new ModuloHasher(), new SimpleEqualizer(), null);
        $this->assertNull($list->cdr());
    }
}
