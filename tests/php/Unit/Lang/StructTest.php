<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use InvalidArgumentException;
use Phel\Lang\Keyword;
use PHPUnit\Framework\TestCase;

final class StructTest extends TestCase
{
    public function testOffsetSet(): void
    {
        $s = FakeStruct::fromKVs(new Keyword('a'), 1, new Keyword('b'), 2);
        $s = $s->put(new Keyword('a'), 3);
        self::assertEquals([new Keyword('a'), 3, new Keyword('b'), 2], $this->toKeyValueList($s));
    }

    public function testOffsetSetInvalidKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $s = FakeStruct::fromKVs(new Keyword('a'), 1, new Keyword('b'), 2);
        $s = $s->put(new Keyword('c'), 2);
    }

    public function testOffsetExists(): void
    {
        $s = FakeStruct::fromKVs(new Keyword('a'), 1, new Keyword('b'), 2);
        self::assertTrue(isset($s[new Keyword('a')]));
    }

    public function testOffsetExistsInvalidKey(): void
    {
        $s = FakeStruct::fromKVs(new Keyword('a'), 1, new Keyword('b'), 2);
        self::assertFalse(isset($s[new Keyword('c')]));
    }

    public function testOffsetUnset(): void
    {
        $s = FakeStruct::fromKVs(new Keyword('a'), 1, new Keyword('b'), 2);
        $s = $s->remove(new Keyword('a'));
        self::assertEquals(FakeStruct::fromKVs(new Keyword('b'), 2), $s);
    }

    public function testOffsetUnsetInvalidKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $s = FakeStruct::fromKVs(new Keyword('a'), 1, new Keyword('b'), 2);
        $s->remove(new Keyword('c'));
    }

    public function testOffsetGet(): void
    {
        $s = FakeStruct::fromKVs(new Keyword('a'), 1, new Keyword('b'), 2);
        self::assertEquals(1, $s[new Keyword('a')]);
    }

    public function testOffsetGetInvalidKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $s = FakeStruct::fromKVs(new Keyword('a'), 1, new Keyword('b'), 2);
        $s[new Keyword('c')];
    }

    private function toKeyValueList($struct)
    {
        $result = [];
        foreach ($struct as $k => $v) {
            $result[] = $k;
            $result[] = $v;
        }

        return $result;
    }
}
