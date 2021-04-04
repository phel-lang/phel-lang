<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use Phel\Lang\PhelArray;
use Phel\Lang\Table;
use Phel\Lang\TypeFactory;
use PHPUnit\Framework\TestCase;

final class TableTest extends TestCase
{
    public function testEmpty(): void
    {
        $table = Table::empty('a', 1, 'b', 2);
        self::assertEquals(0, $table->count());
    }

    public function testFromKv(): void
    {
        $table = Table::fromKVs('a', 1, 'b', 2);
        self::assertEquals(1, $table['a']);
        self::assertEquals(2, $table['b']);
    }

    public function testFromKvUneven(): void
    {
        $this->expectException(\Exception::class);
        $table = Table::fromKVs('a', 1, 'b');
    }

    public function testFromKVArray(): void
    {
        $table = Table::fromKVArray(['a', 1, 'b', 2]);
        self::assertEquals(1, $table['a']);
        self::assertEquals(2, $table['b']);
    }

    public function testOffsetSetExistingValue(): void
    {
        $table = Table::fromKVs('a', 1);
        $table['a'] = 2;
        self::assertEquals(2, $table['a']);
    }

    public function testOffsetSetNewValue(): void
    {
        $table = Table::fromKVs('a', 1);
        $table['b'] = 2;
        self::assertEquals(1, $table['a']);
        self::assertEquals(2, $table['b']);
    }

    public function testOffsetExists(): void
    {
        $table = Table::fromKVs('a', 1);

        self::assertTrue(isset($table['a']));
        self::assertFalse(isset($table['b']));
    }

    public function testOffestUnset(): void
    {
        $table = Table::fromKVs('a', 1);
        unset($table['a']);

        self::assertEquals(0, count($table));
        self::assertNull($table['a']);
    }

    public function testOffsetGet(): void
    {
        $table = Table::fromKVs('a', 1);
        self::assertEquals(1, $table['a']);
    }

    public function testCount(): void
    {
        $table = Table::fromKVs('a', 1);
        $this->assertEquals(1, count($table));
    }

    public function testForeach(): void
    {
        $table = Table::fromKVs('a', 1, 'b', 2);
        $result = [];
        foreach ($table as $k => $v) {
            $result[$k] = $v;
        }

        $this->assertEquals(['a' => 1, 'b' => 2], $result);
    }

    public function testFirst(): void
    {
        $table = Table::fromKVs('a', 1, 'b', 2);
        $this->assertEquals(
            TypeFactory::getInstance()->persistentVectorFromArray(['a', 1]),
            $table->first()
        );
    }

    public function testCdr(): void
    {
        $table = Table::fromKVs('a', 1, 'b', 2, 'c', 3);
        $this->assertEquals(
            PhelArray::create(
                TypeFactory::getInstance()->persistentVectorFromArray(['b', 2]),
                TypeFactory::getInstance()->persistentVectorFromArray(['c', 3])
            ),
            $table->cdr()
        );
    }

    public function testCdrEmpty(): void
    {
        $table = Table::empty();
        $this->assertNull($table->cdr());
    }

    public function testRest(): void
    {
        $table = Table::fromKVs('a', 1, 'b', 2, 'c', 3);
        $this->assertEquals(
            PhelArray::create(
                TypeFactory::getInstance()->persistentVectorFromArray(['b', 2]),
                TypeFactory::getInstance()->persistentVectorFromArray(['c', 3])
            ),
            $table->rest()
        );
    }

    public function testRestEmpty(): void
    {
        $table = Table::empty();
        $this->assertEquals(PhelArray::create(), $table->rest());
    }

    public function testHash(): void
    {
        $table = Table::fromKVs('a', 1);
        $this->assertEquals(crc32(spl_object_hash($table)), $table->hash());
    }

    public function testEquals(): void
    {
        $table1 = Table::fromKVs('a', 1, 'b', 2);
        $table2 = Table::fromKVs('a', 1, 'b', 2);
        $table3 = Table::fromKVs('a', 1, 'b', 2, 'c', 3);

        $this->assertTrue($table1->equals($table1));
        $this->assertTrue($table1->equals($table2));
        $this->assertTrue($table2->equals($table1));
        $this->assertFalse($table1->equals($table3));
        $this->assertFalse($table3->equals($table1));
    }

    public function testToKeyValueList(): void
    {
        $table = Table::fromKVs('a', 1, 'b', 2);
        $this->assertEquals(
            ['a', 1, 'b', 2],
            $table->toKeyValueList()
        );
    }

    public function testToString(): void
    {
        $table = Table::fromKVs('a', 1, 'b', 2);
        $this->assertEquals(
            '@{"a" 1 "b" 2}',
            $table->__toString()
        );
    }

    public function testObjectKey(): void
    {
        $date = new \DateTime();
        $table = Table::fromKVs($date, 1, 'b', 2);

        $this->assertEquals(1, $table[$date]);
    }

    public function testNumberZeroAsValue(): void
    {
        $table = Table::fromKVs('a', 0);
        self::assertEquals(0, $table['a']);
    }
}
