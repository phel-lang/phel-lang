<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\Vector;

use Phel\Lang\Collections\Exceptions\IndexOutOfBoundsException;
use Phel\Lang\Collections\Map\PersistentHashMap;
use Phel\Lang\Collections\Vector\PersistentVector;
use Phel\Lang\Collections\Vector\SubVector;
use PhelTest\Benchmark\Lang\Collections\Map\SimpleEqualizer;
use PhelTest\Unit\Lang\Collections\ModuloHasher;
use PHPUnit\Framework\TestCase;

final class SubVectorTest extends TestCase
{
    public function testCdr(): void
    {
        $subVector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2, 3, 4])
            ->slice(1, 2);

        $this->assertEquals([3], $subVector->cdr()->toArray());
    }

    public function testToArray(): void
    {
        $subVector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2, 3, 4])
            ->slice(1, 2);

        $this->assertEquals([2, 3], $subVector->toArray());
    }

    public function testWithMeta(): void
    {
        $meta = PersistentHashMap::empty(new ModuloHasher(), new SimpleEqualizer());
        $subVector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2, 3, 4])
            ->slice(1, 2);
        $subVectorWithMeta = $subVector->withMeta($meta);

        $this->assertEquals(null, $subVector->getMeta());
        $this->assertEquals($meta, $subVectorWithMeta->getMeta());
    }

    public function testGetIterator(): void
    {
        $subVector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2, 3, 4])
            ->slice(1, 2);

        $result = [];
        foreach ($subVector as $x) {
            $result[] = $x;
        }

        $this->assertEquals([2, 3], $result);
    }

    public function testAppend(): void
    {
        $subVector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2, 3, 4])
            ->slice(1, 2);

        $subVectorAppended = $subVector->append(10);

        $this->assertEquals(2, count($subVector));
        $this->assertEquals(3, count($subVectorAppended));
        $this->assertEquals(10, $subVectorAppended->get(2));
    }

    public function testUpdateOutOfRange(): void
    {
        $this->expectException(IndexOutOfBoundsException::class);
        $subVector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2, 3, 4])
            ->slice(1, 2);

        $subVector->update(3, 10);
    }

    public function testUpdateAsAppend(): void
    {
        $subVector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2, 3, 4])
            ->slice(1, 2);
        $subVectorAppended = $subVector->update(2, 10);

        $this->assertEquals(2, count($subVector));
        $this->assertEquals(3, count($subVectorAppended));
        $this->assertEquals(10, $subVectorAppended->get(2));
    }

    public function testUpdate(): void
    {
        $subVector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2, 3, 4])
            ->slice(1, 2);
        $subVectorUpdated = $subVector->update(0, 10);

        $this->assertEquals(2, count($subVector));
        $this->assertEquals(2, count($subVectorUpdated));
        $this->assertEquals(10, $subVectorUpdated->get(0));
    }

    public function testGet(): void
    {
        $subVector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2, 3, 4])
            ->slice(1, 2);

        $this->assertEquals(2, $subVector->get(0));
        $this->assertEquals(3, $subVector->get(1));
    }

    public function testGetOutOfBound(): void
    {
        $this->expectException(IndexOutOfBoundsException::class);
        $subVector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2, 3, 4])
            ->slice(1, 2);

        $subVector->get(2);
    }

    public function testPopOnEmptySubVector(): void
    {
        $subVector = new SubVector(
            new ModuloHasher(),
            new SimpleEqualizer(),
            null,
            PersistentVector::empty(new ModuloHasher(), new SimpleEqualizer()),
            0,
            0
        );

        $this->assertEquals(
            PersistentVector::empty(new ModuloHasher(), new SimpleEqualizer()),
            $subVector->pop()
        );
    }

    public function testPop(): void
    {
        $subVector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2, 3, 4])
            ->slice(1, 2);
        $subVectorPopped = $subVector->pop();

        $this->assertEquals(2, count($subVector));
        $this->assertEquals(1, count($subVectorPopped));
    }

    public function testSlice(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2, 3, 4])
            ->slice(1, 2)
            ->slice(0, 1);

        $this->assertInstanceOf(SubVector::class, $vector);
        $this->assertEquals(1, count($vector));
    }
}
