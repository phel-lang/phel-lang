<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\Struct;

use InvalidArgumentException;
use Phel\Lang\Collections\Map\PersistentHashMap;
use Phel\Lang\Keyword;
use Phel\Lang\TypeFactory;
use PHPUnit\Framework\TestCase;

final class StructTest extends TestCase
{
    public function testPut(): void
    {
        $s = FakeStruct::fromKVs(new Keyword('a'), 1, new Keyword('b'), 2);
        $s = $s->put(new Keyword('a'), 3);
        self::assertEquals([new Keyword('a'), 3, new Keyword('b'), 2], $this->toKeyValueList($s));
    }

    public function testPutInvalidKey(): void
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

    public function testRemove(): void
    {
        $s = FakeStruct::fromKVs(new Keyword('a'), 1, new Keyword('b'), 2);
        $s = $s->remove(new Keyword('a'));
        self::assertEquals(FakeStruct::fromKVs(new Keyword('b'), 2), $s);
    }

    public function testRemoveInvalidKey(): void
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

    public function testCount(): void
    {
        $s = FakeStruct::fromKVs(new Keyword('a'), 1, new Keyword('b'), 2);
        self::assertEquals(2, count($s));
    }

    public function testEqualsOtherType(): void
    {
        $s = FakeStruct::fromKVs(new Keyword('a'), 1, new Keyword('b'), 2);
        $map = PersistentHashMap::fromArray(
            TypeFactory::getInstance()->getHasher(),
            TypeFactory::getInstance()->getEqualizer(),
            [new Keyword('a'), 1, new Keyword('b'), 2]
        );

        self::assertFalse($s->equals($map));
    }

    public function testEqualsDifferentValues(): void
    {
        $s1 = FakeStruct::fromKVs(new Keyword('a'), 1, new Keyword('b'), 2);
        $s2 = FakeStruct::fromKVs(new Keyword('a'), 1, new Keyword('b'), 3);

        self::assertFalse($s1->equals($s2));
        self::assertFalse($s2->equals($s1));
    }

    public function testEqualsSameValues(): void
    {
        $s1 = FakeStruct::fromKVs(new Keyword('a'), 1, new Keyword('b'), 2);
        $s2 = FakeStruct::fromKVs(new Keyword('a'), 1, new Keyword('b'), 2);

        self::assertTrue($s1->equals($s2));
        self::assertTrue($s2->equals($s1));
    }

    public function testAllowedKeys(): void
    {
        $s = FakeStruct::fromKVs(new Keyword('a'), 1, new Keyword('b'), 2);

        self::assertEquals([new Keyword('a'), new Keyword('b')], $s->getAllowedKeys());
    }

    public function testWithMeta(): void
    {
        $meta = TypeFactory::getInstance()->emptyPersistentMap();
        $s = FakeStruct::fromKVs(new Keyword('a'), 1, new Keyword('b'), 2);
        $sWithMeta = $s->withMeta($meta);

        $this->assertEquals(null, $s->getMeta());
        $this->assertEquals($meta, $sWithMeta->getMeta());
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
