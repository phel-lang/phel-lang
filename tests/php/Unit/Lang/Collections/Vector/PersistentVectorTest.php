<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\Vector;

use Phel\Lang\Collections\Vector\PersistentVector;
use Phel\Lang\Collections\Vector\RangeIterator;
use PhelTest\Unit\Lang\Collections\ModuloHasher;
use PhelTest\Unit\Lang\Collections\SimpleEqualizer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PersistentVectorTest extends TestCase
{
    public function testAppendToTail(): void
    {
        $vEmpty = PersistentVector::empty(new ModuloHasher(), new SimpleEqualizer());
        $v1 = $vEmpty->append('a');

        $this->assertEquals(0, $vEmpty->count());
        $this->assertEquals(1, $v1->count());
        $this->assertEquals('a', $v1->get(0));
    }

    public function testAppendTailIsFull(): void
    {
        $v1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, 31));
        $v2 = $v1->append(32);

        $this->assertEquals(32, $v1->count());
        $this->assertEquals(33, $v2->count());
        $this->assertEquals(32, $v2->get(32));
    }

    public function testAppendOverflowRoot(): void
    {
        $initialLength = 32 + (32 * 32) - 1;
        $v1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, $initialLength));
        $v2 = $v1->append(1056);

        $this->assertEquals(1056, $v1->count());
        $this->assertEquals(1057, $v2->count());
        $this->assertEquals(1056, $v2->get(1056));
    }

    public function testAppendTailIsFullSecondLevel(): void
    {
        $initialLength = 32 + (32 * 32) + 32 - 1;
        $v1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, $initialLength));
        $v2 = $v1->append($initialLength + 1);

        $this->assertEquals($initialLength + 1, $v1->count());
        $this->assertEquals($initialLength + 2, $v2->count());
        $this->assertEquals($initialLength + 1, $v2->get($initialLength + 1));
    }

    public function testAppendTailIsFullThirdLevel(): void
    {
        $initialLength = 32 + (32 * 32) + (32 * 32 * 32) - 1;
        $v1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, $initialLength));
        $v2 = $v1->append($initialLength + 1);

        $this->assertEquals($initialLength + 1, $v1->count());
        $this->assertEquals($initialLength + 2, $v2->count());
        $this->assertEquals($initialLength + 1, $v2->get($initialLength + 1));
    }

    public function testUpdateOutOfRange(): void
    {
        $this->expectException(RuntimeException::class);
        $v = PersistentVector::empty(new ModuloHasher(), new SimpleEqualizer());

        $v->update(1, 10);
    }

    public function testUpdateAppend(): void
    {
        $vEmpty = PersistentVector::empty(new ModuloHasher(), new SimpleEqualizer());
        $v1 = $vEmpty->update(0, 10);

        $this->assertEquals(0, $vEmpty->count());
        $this->assertEquals(1, $v1->count());
        $this->assertEquals(10, $v1->get(0));
    }

    public function testUpdateInTail(): void
    {
        $v1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [10]);
        $v2 = $v1->update(0, 20);

        $this->assertEquals(1, $v1->count());
        $this->assertEquals(1, $v2->count());
        $this->assertEquals(10, $v1->get(0));
        $this->assertEquals(20, $v2->get(0));
    }

    public function testUpdateInLevelTree(): void
    {
        $v1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, 32));
        $v2 = $v1->update(0, 20);

        $this->assertEquals(33, $v1->count());
        $this->assertEquals(33, $v2->count());
        $this->assertEquals(0, $v1->get(0));
        $this->assertEquals(20, $v2->get(0));
    }

    public function testGetOutOfRange(): void
    {
        $this->expectException(RuntimeException::class);
        $vEmpty = PersistentVector::empty(new ModuloHasher(), new SimpleEqualizer());

        $vEmpty->get(0);
    }

    public function testPopOnEmptyVector(): void
    {
        $this->expectException(RuntimeException::class);
        $vEmpty = PersistentVector::empty(new ModuloHasher(), new SimpleEqualizer());

        $vEmpty->pop();
    }

    public function testPopOnOneElementVector(): void
    {
        $v1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1]);
        $vEmpty = $v1->pop();

        $this->assertEquals(1, $v1->count());
        $this->assertEquals(0, $vEmpty->count());
    }

    public function testPopFromTail(): void
    {
        $v1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2]);
        $v2 = $v1->pop();

        $this->assertEquals(2, $v1->count());
        $this->assertEquals(1, $v2->count());
        $this->assertEquals(1, $v2->get(0));
    }

    public function testPopFromTreeLevelOne(): void
    {
        $v1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, 32));
        $v2 = $v1->pop();

        $this->assertEquals(33, $v1->count());
        $this->assertEquals(32, $v2->count());
    }

    public function testPopFromTreeLevelTwo(): void
    {
        $length = 32 + (32 * 32);
        $v1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, $length));
        $v2 = $v1->pop();

        $this->assertEquals($length + 1, $v1->count());
        $this->assertEquals($length, $v2->count());
    }

    public function testPopFromTreeLevelTwo2(): void
    {
        $length = (32 * 32);
        $v1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, $length));
        $v2 = $v1->pop();

        $this->assertEquals($length + 1, $v1->count());
        $this->assertEquals($length, $v2->count());
    }

    public function testPopFromTreeLevelThree(): void
    {
        $length = (32 * 32) + (32 * 32 * 31) + 32;
        $v1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, $length));
        $v2 = $v1->pop();

        $this->assertEquals($length + 1, $v1->count());
        $this->assertEquals($length, $v2->count());
    }

    public function testToArrayTail(): void
    {
        $arr = [1, 2, 3];
        $this->assertEquals($arr, PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), $arr)->toArray());
    }

    public function testToArrayLevelOne(): void
    {
        $arr = range(0, 32);
        $this->assertEquals($arr, PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), $arr)->toArray());
    }

    public function testGetIteratorOnEmptyVector(): void
    {
        $v = PersistentVector::empty(new ModuloHasher(), new SimpleEqualizer());
        $result = [];
        foreach ($v as $x) {
            $result[] = $x;
        }

        $this->assertEquals([], $result);
    }

    public function testGetIteratorOnTailOnlyVector(): void
    {
        $v = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2]);
        $result = [];
        foreach ($v as $x) {
            $result[] = $x;
        }

        $this->assertEquals([1, 2], $result);
    }

    public function testGetIteratorOnTreeVector(): void
    {
        $v = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, 32));
        $result = [];
        $indices = [];
        foreach ($v as $index => $x) {
            $indices[] = $index;
            $result[] = $x;
        }

        $this->assertEquals(range(0, 32), $result);
        $this->assertEquals(range(0, 32), $indices);
    }

    public function testGetRangeIterator(): void
    {
        $this->assertInstanceOf(RangeIterator::class, PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2, 3])->getRangeIterator(0, 2));
    }
}
