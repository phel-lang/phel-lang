<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\Vector;

use Phel\Lang\Collections\Exceptions\IndexOutOfBoundsException;
use Phel\Lang\Collections\Vector\PersistentVector;
use Phel\Lang\Collections\Vector\RangeIterator;
use Phel\Lang\Collections\Vector\SubVector;
use Phel\Lang\Collections\Vector\TransientVector;
use Phel\Lang\TypeFactory;
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
        $this->expectException(IndexOutOfBoundsException::class);
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
        $this->expectException(IndexOutOfBoundsException::class);
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

    public function testAddMetaData(): void
    {
        $meta = TypeFactory::getInstance()->emptyPersistentMap();
        $vector = PersistentVector::empty(new ModuloHasher(), new SimpleEqualizer());
        $vectorWithMeta = $vector->withMeta($meta);

        $this->assertEquals(null, $vector->getMeta());
        $this->assertEquals($meta, $vectorWithMeta->getMeta());
    }

    public function testCdrOnEmptyVector(): void
    {
        $vector = PersistentVector::empty(new ModuloHasher(), new SimpleEqualizer());
        $this->assertNull($vector->cdr());
    }

    public function testCdrOnOneElementVector(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1]);
        $this->assertNull($vector->cdr());
    }

    public function testCdrOnTwoElementVector(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2]);

        $result = [];
        foreach ($vector->cdr() as $x) {
            $result[] = $x;
        }
        $this->assertEquals([2], $result);
    }

    public function testSlice(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2, 3, 4])
            ->slice(1, 2);

        $this->assertInstanceOf(SubVector::class, $vector);
        $this->assertEquals(2, count($vector));
    }

    public function testSliceToEmpty(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2, 3, 4])
            ->slice(1, 0);

        $this->assertInstanceOf(PersistentVector::class, $vector);
        $this->assertEquals(0, count($vector));
    }

    public function testSliceWithoutLength(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2, 3, 4])
            ->slice(1);

        $this->assertInstanceOf(SubVector::class, $vector);
        $this->assertEquals(3, count($vector));
    }

    public function testAsTransient(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2, 3, 4])
            ->asTransient();

        $this->assertInstanceOf(TransientVector::class, $vector);
        $this->assertEquals(4, count($vector));
    }

    public function testFirstOnEmpty(): void
    {
        $vector = PersistentVector::empty(new ModuloHasher(), new SimpleEqualizer());
        $this->assertNull($vector->first());
    }

    public function testFirstSingleElementVector(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1]);
        $this->assertEquals(1, $vector->first());
    }

    public function testInvoke(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2, 3, 4]);

        $this->assertEquals(2, $vector(1));
    }

    public function testRestOnEmptyVector(): void
    {
        $vector = PersistentVector::empty(new ModuloHasher(), new SimpleEqualizer());
        $this->assertEquals(
            PersistentVector::empty(new ModuloHasher(), new SimpleEqualizer()),
            $vector->rest()
        );
    }

    public function testRestOnOneElementVector(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1]);
        $this->assertEquals(
            PersistentVector::empty(new ModuloHasher(), new SimpleEqualizer()),
            $vector->rest()
        );
    }

    public function testRestOnTwoElementVector(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2]);

        $result = [];
        foreach ($vector->rest() as $x) {
            $result[] = $x;
        }
        $this->assertEquals([2], $result);
    }

    public function testHash(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2]);
        $this->assertEquals(994, $vector->hash());
    }

    public function testEqualsOtherType(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2]);

        $this->assertFalse($vector->equals([1, 2]));
    }

    public function testEqualsDifferentLength(): void
    {
        $vector1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2]);
        $vector2 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2, 3]);

        $this->assertFalse($vector1->equals($vector2));
        $this->assertFalse($vector2->equals($vector1));
    }

    public function testEqualsDifferentValues(): void
    {
        $vector1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2]);
        $vector2 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 3]);

        $this->assertFalse($vector1->equals($vector2));
        $this->assertFalse($vector2->equals($vector1));
    }

    public function testEqualsSameValues(): void
    {
        $vector1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2]);
        $vector2 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2]);

        $this->assertTrue($vector1->equals($vector2));
        $this->assertTrue($vector2->equals($vector1));
    }

    public function testOffsetGet(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2]);

        $this->assertEquals(1, $vector[0]);
        $this->assertEquals(2, $vector[1]);
    }

    public function testOffsetExists(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2]);

        $this->assertTrue(isset($vector[0]));
        $this->assertTrue(isset($vector[1]));
        $this->assertFalse(isset($vector[2]));
    }

    public function testPush(): void
    {
        $vector1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2]);
        $vector2 = $vector1->push(3);

        $this->assertEquals(2, count($vector1));
        $this->assertEquals(3, count($vector2));
        $this->assertEquals(3, $vector2->get(2));
    }

    public function testConcat(): void
    {
        $vector1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2]);
        $vector2 = $vector1->concat([3, 4]);

        $this->assertEquals(2, count($vector1));
        $this->assertEquals(4, count($vector2));
        $this->assertEquals(3, $vector2->get(2));
        $this->assertEquals(4, $vector2->get(3));
    }
}
