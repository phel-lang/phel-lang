<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\Vector;

use Phel\Lang\Collections\Exceptions\IndexOutOfBoundsException;
use Phel\Lang\Collections\Vector\TransientVector;
use PhelTest\Unit\Lang\Collections\ModuloHasher;
use PhelTest\Unit\Lang\Collections\SimpleEqualizer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TransientVectorTest extends TestCase
{
    public function testAppendToTail(): void
    {
        $vEmpty = TransientVector::empty(new ModuloHasher(), new SimpleEqualizer());
        $v1 = $vEmpty->append('a');

        $this->assertEquals(1, $vEmpty->count());
        $this->assertEquals(1, $v1->count());
        $this->assertEquals('a', $v1->get(0));
    }

    public function testAppendTailIsFull(): void
    {
        $v1 = TransientVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, 31));
        $v2 = $v1->append(32);

        $this->assertEquals(33, $v1->count());
        $this->assertEquals(33, $v2->count());
        $this->assertEquals(32, $v2->get(32));
    }

    public function testAppendOverflowRoot(): void
    {
        $initialLength = 32 + (32 * 32) - 1;
        $v1 = TransientVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, $initialLength));
        $v2 = $v1->append(1056);

        $this->assertEquals(1057, $v1->count());
        $this->assertEquals(1057, $v2->count());
        $this->assertEquals(1056, $v2->get(1056));
    }

    public function testAppendTailIsFullSecondLevel(): void
    {
        $initialLength = 32 + (32 * 32) + 32 - 1;
        $v1 = TransientVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, $initialLength));
        $v2 = $v1->append($initialLength + 1);

        $this->assertEquals($initialLength + 2, $v1->count());
        $this->assertEquals($initialLength + 2, $v2->count());
        $this->assertEquals($initialLength + 1, $v2->get($initialLength + 1));
    }

    public function testAppendTailIsFullThirdLevel(): void
    {
        $initialLength = 32 + (32 * 32) + (32 * 32 * 32) - 1;
        $v1 = TransientVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, $initialLength));
        $v2 = $v1->append($initialLength + 1);

        $this->assertEquals($initialLength + 2, $v1->count());
        $this->assertEquals($initialLength + 2, $v2->count());
        $this->assertEquals($initialLength + 1, $v2->get($initialLength + 1));
    }

    public function testUpdateOutOfRange(): void
    {
        $this->expectException(IndexOutOfBoundsException::class);
        $v = TransientVector::empty(new ModuloHasher(), new SimpleEqualizer());

        $v->update(1, 10);
    }

    public function testUpdateAppend(): void
    {
        $vEmpty = TransientVector::empty(new ModuloHasher(), new SimpleEqualizer());
        $v1 = $vEmpty->update(0, 10);

        $this->assertEquals(1, $vEmpty->count());
        $this->assertEquals(1, $v1->count());
        $this->assertEquals(10, $vEmpty->get(0));
        $this->assertEquals(10, $v1->get(0));
    }

    public function testUpdateInTail(): void
    {
        $v1 = TransientVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [10]);
        $v2 = $v1->update(0, 20);

        $this->assertEquals(1, $v1->count());
        $this->assertEquals(1, $v2->count());
        $this->assertEquals(20, $v1->get(0));
        $this->assertEquals(20, $v2->get(0));
    }

    public function testUpdateInLevelTree(): void
    {
        $v1 = TransientVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, 32));
        $v2 = $v1->update(0, 20);

        $this->assertEquals(33, $v1->count());
        $this->assertEquals(33, $v2->count());
        $this->assertEquals(20, $v1->get(0));
        $this->assertEquals(20, $v2->get(0));
    }

    public function testGetOutOfRange(): void
    {
        $this->expectException(IndexOutOfBoundsException::class);
        $vEmpty = TransientVector::empty(new ModuloHasher(), new SimpleEqualizer());

        $vEmpty->get(0);
    }

    public function testPopOnEmptyVector(): void
    {
        $this->expectException(RuntimeException::class);
        $vEmpty = TransientVector::empty(new ModuloHasher(), new SimpleEqualizer());

        $vEmpty->pop();
    }

    public function testPopOnOneElementVector(): void
    {
        $v1 = TransientVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1]);
        $vEmpty = $v1->pop();

        $this->assertEquals(0, $v1->count());
        $this->assertEquals(0, $vEmpty->count());
    }

    public function testPopFromTail(): void
    {
        $v1 = TransientVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2]);
        $v2 = $v1->pop();

        $this->assertEquals(1, $v1->count());
        $this->assertEquals(1, $v2->count());
        $this->assertEquals(1, $v2->get(0));
    }

    public function testPopFromTreeLevelOne(): void
    {
        $v1 = TransientVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, 32));
        $v2 = $v1->pop();

        $this->assertEquals(32, $v1->count());
        $this->assertEquals(32, $v2->count());
    }

    public function testPopFromTreeLevelTwo(): void
    {
        $length = 32 + (32 * 32);
        $v1 = TransientVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, $length));
        $v2 = $v1->pop();

        $this->assertEquals($length, $v1->count());
        $this->assertEquals($length, $v2->count());
    }

    public function testPopFromTreeLevelTwo2(): void
    {
        $length = (32 * 32);
        $v1 = TransientVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, $length));
        $v2 = $v1->pop();

        $this->assertEquals($length, $v1->count());
        $this->assertEquals($length, $v2->count());
    }

    public function testPopFromTreeLevelThree(): void
    {
        $length = (32 * 32) + (32 * 32 * 31) + 32;
        $v1 = TransientVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, $length));
        $v2 = $v1->pop();

        $this->assertEquals($length, $v1->count());
        $this->assertEquals($length, $v2->count());
    }
}
