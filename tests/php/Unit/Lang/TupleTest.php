<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use DateTime;
use InvalidArgumentException;
use Phel\Lang\Tuple;
use PHPUnit\Framework\TestCase;

final class TupleTest extends TestCase
{
    public function testCreate(): void
    {
        $date = new DateTime();
        $t = Tuple::create('a', 1, $date);

        $this->assertEquals('a', $t[0]);
        $this->assertEquals(1, $t[1]);
        $this->assertEquals($date, $t[2]);
        $this->assertEquals(3, count($t));
        $this->assertFalse($t->isUsingBracket());
    }

    public function testCreateBracket(): void
    {
        $date = new DateTime();
        $t = Tuple::createBracket('a', 1, $date);

        $this->assertEquals('a', $t[0]);
        $this->assertEquals(1, $t[1]);
        $this->assertEquals($date, $t[2]);
        $this->assertEquals(3, count($t));
        $this->assertTrue($t->isUsingBracket());
    }

    public function testOffsetSet(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $t = Tuple::create('a', 1);
        $t[0] = 'b';
    }

    public function testOffsetExists(): void
    {
        $t = Tuple::create('a', 1);
        $this->assertTrue(isset($t[0]));
        $this->assertTrue(isset($t[1]));
        $this->assertFalse(isset($t[2]));
    }

    public function testOffsetUnset(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $t = Tuple::create('a', 1);
        unset($t[0]);
    }

    public function testOffsetGet(): void
    {
        $t = Tuple::create('a', 1);
        $this->assertEquals('a', $t[0]);
        $this->assertEquals(1, $t[1]);
        $this->assertNull($t[2]);
    }

    public function testCount(): void
    {
        $t = Tuple::create('a', 1);
        $this->assertEquals(2, count($t));
    }

    public function testForeach(): void
    {
        $t = Tuple::create('a', 1);
        $result = [];
        foreach ($t as $k => $v) {
            $result[$k] = $v;
        }

        $this->assertEquals(['a', 1], $result);
    }

    public function testUpdateNotInRange(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $t = Tuple::create('a', 1);
        $t->update(3, 10);
    }

    public function testUpdateAppend(): void
    {
        $t1 = Tuple::create('a', 1);
        $t2 = $t1->update(2, 10);

        $this->assertEquals(Tuple::create('a', 1), $t1);
        $this->assertEquals(Tuple::create('a', 1, 10), $t2);
    }

    public function testUpdateRemove(): void
    {
        $t1 = Tuple::create('a', 1);
        $t2 = $t1->update(1, null);

        $this->assertEquals(Tuple::create('a', 1), $t1);
        $this->assertEquals(Tuple::create('a'), $t2);
    }

    public function testUpdateReplace(): void
    {
        $t1 = Tuple::create('a', 1);
        $t2 = $t1->update(1, 2);

        $this->assertEquals(Tuple::create('a', 1), $t1);
        $this->assertEquals(Tuple::create('a', 2), $t2);
    }

    public function testSlice(): void
    {
        $t1 = Tuple::create('a', 'b', 'c', 'd', 'e');
        $t2 = $t1->slice(0, 3);

        $this->assertEquals(Tuple::create('a', 'b', 'c', 'd', 'e'), $t1);
        $this->assertEquals(Tuple::create('a', 'b', 'c'), $t2);
    }

    public function testCons(): void
    {
        $t1 = Tuple::create('a', 1);
        $t2 = $t1->cons('x');

        $this->assertEquals(Tuple::create('a', 1), $t1);
        $this->assertEquals(Tuple::create('x', 'a', 1), $t2);
    }

    public function testHash(): void
    {
        $t1 = Tuple::create('a', 1);
        $this->assertEquals(crc32(spl_object_hash($t1)), $t1->hash());
    }

    public function testEquals(): void
    {
        $t1 = Tuple::create('a', 1);
        $t2 = Tuple::create('a', 1);
        $t3 = Tuple::create('a', 1, 'b');

        $this->assertTrue($t1->equals($t2));
        $this->assertTrue($t2->equals($t1));
        $this->assertFalse($t1->equals($t3));
        $this->assertFalse($t3->equals($t1));
    }

    public function testFirstEmpty(): void
    {
        $t1 = Tuple::create();

        $this->assertNull($t1->first());
    }

    public function testFirst(): void
    {
        $t1 = Tuple::create('a', 1);
        $this->assertEquals('a', $t1->first());
    }

    public function testCdrEmpty(): void
    {
        $t1 = Tuple::create();
        $this->assertNull($t1->cdr());
    }

    public function testCdr(): void
    {
        $t1 = Tuple::create('a', 1, 'b');
        $this->assertEquals(Tuple::create(1, 'b'), $t1->cdr());
    }

    public function testRestEmpty(): void
    {
        $t1 = Tuple::create();
        $this->assertEquals(Tuple::create(), $t1->rest());
    }

    public function testRest(): void
    {
        $t1 = Tuple::create('a', 1, 'b');
        $this->assertEquals(Tuple::create(1, 'b'), $t1->rest());
    }

    public function testPush(): void
    {
        $t1 = Tuple::create('a', 1);
        $t2 = $t1->push(10);

        $this->assertEquals(Tuple::create('a', 1), $t1);
        $this->assertEquals(Tuple::create('a', 1, 10), $t2);
    }

    public function testConcat(): void
    {
        $t1 = Tuple::create('a', 1);
        $t2 = Tuple::create('b', 2);
        $t3 = $t1->concat($t2);

        $this->assertEquals(Tuple::create('a', 1), $t1);
        $this->assertEquals(Tuple::create('b', 2), $t2);
        $this->assertEquals(Tuple::create('a', 1, 'b', 2), $t3);
    }

    public function testToArray(): void
    {
        $t1 = Tuple::create('a', 1);
        $this->assertEquals(['a', 1], $t1->toArray());
    }

    public function testToString(): void
    {
        $t1 = Tuple::create('a', 1);
        $this->assertEquals('("a" 1)', $t1->__toString());
    }
}
