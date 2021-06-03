<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use Phel\Lang\PhelArray;
use Phel\Lang\Table;
use Phel\Lang\TypeFactory;
use PHPUnit\Framework\TestCase;

final class TableTest extends TestCase
{
    public function test_empty(): void
    {
        $table = Table::empty('a', 1, 'b', 2);
        self::assertEquals(0, $table->count());
    }

    public function test_from_kv(): void
    {
        $table = Table::fromKVs('a', 1, 'b', 2);
        self::assertEquals(1, $table['a']);
        self::assertEquals(2, $table['b']);
    }

    public function test_from_kv_uneven(): void
    {
        $this->expectException(\Exception::class);
        $table = Table::fromKVs('a', 1, 'b');
    }

    public function test_from_kv_array(): void
    {
        $table = Table::fromKVArray(['a', 1, 'b', 2]);
        self::assertEquals(1, $table['a']);
        self::assertEquals(2, $table['b']);
    }

    public function test_offset_set_existing_value(): void
    {
        $table = Table::fromKVs('a', 1);
        $table['a'] = 2;
        self::assertEquals(2, $table['a']);
    }

    public function test_offset_set_new_value(): void
    {
        $table = Table::fromKVs('a', 1);
        $table['b'] = 2;
        self::assertEquals(1, $table['a']);
        self::assertEquals(2, $table['b']);
    }

    public function test_offset_exists(): void
    {
        $table = Table::fromKVs('a', 1);

        self::assertTrue(isset($table['a']));
        self::assertFalse(isset($table['b']));
    }

    public function test_offest_unset(): void
    {
        $table = Table::fromKVs('a', 1);
        unset($table['a']);

        self::assertEquals(0, count($table));
        self::assertNull($table['a']);
    }

    public function test_offset_get(): void
    {
        $table = Table::fromKVs('a', 1);
        self::assertEquals(1, $table['a']);
    }

    public function test_count(): void
    {
        $table = Table::fromKVs('a', 1);
        $this->assertEquals(1, count($table));
    }

    public function test_foreach(): void
    {
        $table = Table::fromKVs('a', 1, 'b', 2);
        $result = [];
        foreach ($table as $k => $v) {
            $result[$k] = $v;
        }

        $this->assertEquals(['a' => 1, 'b' => 2], $result);
    }

    public function test_first(): void
    {
        $table = Table::fromKVs('a', 1, 'b', 2);
        $this->assertEquals(
            TypeFactory::getInstance()->persistentVectorFromArray(['a', 1]),
            $table->first()
        );
    }

    public function test_cdr(): void
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

    public function test_cdr_empty(): void
    {
        $table = Table::empty();
        $this->assertNull($table->cdr());
    }

    public function test_rest(): void
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

    public function test_rest_empty(): void
    {
        $table = Table::empty();
        $this->assertEquals(PhelArray::create(), $table->rest());
    }

    public function test_hash(): void
    {
        $table = Table::fromKVs('a', 1);
        $this->assertEquals(crc32(spl_object_hash($table)), $table->hash());
    }

    public function test_equals(): void
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

    public function test_to_key_value_list(): void
    {
        $table = Table::fromKVs('a', 1, 'b', 2);
        $this->assertEquals(
            ['a', 1, 'b', 2],
            $table->toKeyValueList()
        );
    }

    public function test_to_string(): void
    {
        $table = Table::fromKVs('a', 1, 'b', 2);
        $this->assertEquals(
            '@{"a" 1 "b" 2}',
            $table->__toString()
        );
    }

    public function test_object_key(): void
    {
        $date = new \DateTime();
        $table = Table::fromKVs($date, 1, 'b', 2);

        $this->assertEquals(1, $table[$date]);
    }

    public function test_number_zero_as_value(): void
    {
        $table = Table::fromKVs('a', 0);
        self::assertEquals(0, $table['a']);
    }
}
