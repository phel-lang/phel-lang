<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use InvalidArgumentException;
use Phel\Lang\Struct;
use PHPUnit\Framework\TestCase;

final class StructTestTable extends Struct
{
    public function getAllowedKeys(): array
    {
        return ['a', 'b'];
    }
}

final class StructTest extends TestCase
{
    public function testOffsetSet(): void
    {
        $s = StructTestTable::fromKVs('a', 1, 'b', 2);
        $s['a'] = 2;
        $this->assertEquals(['a', 2, 'b', 2], $s->toKeyValueList());
    }

    public function testOffsetSetInvalidKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $s = StructTestTable::fromKVs('a', 1, 'b', 2);
        $s['c'] = 2;
    }

    public function testOffsetExists(): void
    {
        $s = StructTestTable::fromKVs('a', 1, 'b', 2);
        $this->assertTrue(isset($s['a']));
    }

    public function testOffsetExistsInvalidKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $s = StructTestTable::fromKVs('a', 1, 'b', 2);
        isset($s['c']);
    }

    public function testOffsetUnset(): void
    {
        $s = StructTestTable::fromKVs('a', 1, 'b', 2);
        unset($s['a']);
        $this->assertEquals(StructTestTable::fromKVs('b', 2), $s);
    }

    public function testOffsetUnsetInvalidKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $s = StructTestTable::fromKVs('a', 1, 'b', 2);
        unset($s['c']);
    }

    public function testOffsetGet(): void
    {
        $s = StructTestTable::fromKVs('a', 1, 'b', 2);
        $this->assertEquals(1, $s['a']);
    }

    public function testOffsetGetInvalidKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $s = StructTestTable::fromKVs('a', 1, 'b', 2);
        $s['c'];
    }
}
