<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use InvalidArgumentException;
use Phel\Lang\PhelArray;
use PHPUnit\Framework\TestCase;

final class PhelArrayTest extends TestCase
{
    public function testOffsetSet()
    {
        $arr = new PhelArray([1]);
        $arr[0] = 10;
        $this->assertEquals(10, $arr[0]);
    }

    public function testOffsetSetEnd()
    {
        $arr = new PhelArray([]);
        $arr[2] = 10;
        $this->assertEquals(null, $arr[0]);
        $this->assertEquals(null, $arr[1]);
        $this->assertEquals(10, $arr[2]);
    }


    public function testOffsetSetSmallerZero()
    {
        $this->expectException(InvalidArgumentException::class);
        $arr = new PhelArray([]);
        $arr[-1] = 10;
    }

    public function testOffsetExists()
    {
        $arr = new PhelArray([1]);

        $this->assertTrue(isset($arr[0]));
        $this->assertFalse(isset($arr[1]));
    }

    public function testOffsetUnset()
    {
        $arr = new PhelArray([1, 2, 3]);

        unset($arr[1]);
        $this->assertEquals([1, 3], $arr->toPhpArray());
    }

    public function testOffsetUnsetOutOfBound1()
    {
        $this->expectException(InvalidArgumentException::class);
        $arr = new PhelArray([1, 2, 3]);
        unset($arr[-1]);
    }

    public function testOffsetUnsetOutOfBound2()
    {
        $this->expectException(InvalidArgumentException::class);
        $arr = new PhelArray([1, 2, 3]);
        unset($arr[3]);
    }

    public function testOffsetGet()
    {
        $arr = new PhelArray([1]);
        $this->assertEquals(1, $arr[0]);
        $this->assertNull($arr[1]);
    }

    public function testCount()
    {
        $arr = new PhelArray([1]);
        $this->assertEquals(1, count($arr));
    }

    public function testForeach()
    {
        $arr = new PhelArray([1, 2, 3]);

        $result = [];
        foreach ($arr as $x) {
            $result[] = $x;
        }

        $this->assertEquals([1, 2, 3], $result);
    }

    public function testEquals()
    {
        $arr1 = new PhelArray([1, 2, 3]);
        $arr2 = new PhelArray([1, 2, 3]);

        $this->assertTrue($arr1->equals($arr1));
        $this->assertTrue($arr1->equals($arr2));
        $this->assertTrue($arr2->equals($arr1));
    }

    public function testNotEquals()
    {
        $arr1 = new PhelArray([1, 2, 3]);
        $arr2 = new PhelArray([]);

        $this->assertFalse($arr1->equals($arr2));
        $this->assertFalse($arr2->equals($arr1));
    }

    public function testHash()
    {
        $arr = new PhelArray([1, 2, 3]);
        $this->assertEquals(spl_object_hash($arr), $arr->hash());
    }

    public function testSlice()
    {
        $arr = new PhelArray([1, 2, 3]);
        $this->assertEquals(new PhelArray([2, 3]), $arr->slice(1));
        $this->assertEquals(new PhelArray([2]), $arr->slice(1, 1));
    }

    public function testCons()
    {
        $arr = new PhelArray([1, 2, 3]);
        $this->assertEquals(new PhelArray([0, 1, 2, 3]), $arr->cons(0));
    }

    public function testToPhpArray()
    {
        $this->assertEquals([1, 2, 3], (new PhelArray([1, 2, 3]))->toPhpArray());
    }

    public function testFirst()
    {
        $this->assertEquals(1, (new PhelArray([1, 2, 3]))->first());
        $this->assertNull((new PhelArray([]))->first());
    }

    public function testCdr()
    {
        $this->assertEquals(new PhelArray([2, 3]), (new PhelArray([1, 2, 3]))->cdr());
        $this->assertNull((new PhelArray([]))->cdr());
    }

    public function testRest()
    {
        $this->assertEquals(new PhelArray([2, 3]), (new PhelArray([1, 2, 3]))->rest());
        $this->assertEquals(new PhelArray([]), (new PhelArray([]))->rest());
    }

    public function testPop()
    {
        $arr = new PhelArray([1, 2, 3]);
        $x = $arr->pop();
        $this->assertEquals(3, $x);
        $this->assertEquals(new PhelArray([1, 2]), $arr);
    }

    public function testRemove()
    {
        $arr = new PhelArray([1, 2, 3]);
        $this->assertEquals(new PhelArray([2]), $arr->remove(1, 1));
        $this->assertEquals(new PhelArray([3]), $arr->remove(1));
    }

    public function testPush()
    {
        $arr = new PhelArray([]);
        $this->assertEquals(new PhelArray([1]), $arr->push(1));
    }

    public function testConcat()
    {
        $arr1 = new PhelArray([1, 2]);
        $arr2 = new PhelArray([3, 4]);

        $arr1->concat($arr2);

        $this->assertEquals(new PhelArray([1, 2, 3, 4]), $arr1);
        $this->assertEquals(new PhelArray([3, 4]), $arr2);
    }

    public function testToString()
    {
        $this->assertEquals('@[1 2 3]', (new PhelArray([1, 2, 3]))->__toString());
    }
}
