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
    public function test_put(): void
    {
        $s = FakeStruct::fromKVs(new Keyword('a'), 1, new Keyword('b'), 2);
        $s = $s->put(new Keyword('a'), 3);
        self::assertEquals([new Keyword('a'), 3, new Keyword('b'), 2], $this->toKeyValueList($s));
    }

    public function test_put_invalid_key(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $s = FakeStruct::fromKVs(new Keyword('a'), 1, new Keyword('b'), 2);
        $s = $s->put(new Keyword('c'), 2);
    }

    public function test_offset_exists(): void
    {
        $s = FakeStruct::fromKVs(new Keyword('a'), 1, new Keyword('b'), 2);
        self::assertTrue(isset($s[new Keyword('a')]));
    }

    public function test_offset_exists_invalid_key(): void
    {
        $s = FakeStruct::fromKVs(new Keyword('a'), 1, new Keyword('b'), 2);
        self::assertFalse(isset($s[new Keyword('c')]));
    }

    public function test_remove(): void
    {
        $s = FakeStruct::fromKVs(new Keyword('a'), 1, new Keyword('b'), 2);
        $s = $s->remove(new Keyword('a'));
        self::assertEquals(FakeStruct::fromKVs(new Keyword('b'), 2), $s);
    }

    public function test_remove_invalid_key(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $s = FakeStruct::fromKVs(new Keyword('a'), 1, new Keyword('b'), 2);
        $s->remove(new Keyword('c'));
    }

    public function test_offset_get(): void
    {
        $s = FakeStruct::fromKVs(new Keyword('a'), 1, new Keyword('b'), 2);
        self::assertEquals(1, $s[new Keyword('a')]);
    }

    public function test_offset_get_invalid_key(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $s = FakeStruct::fromKVs(new Keyword('a'), 1, new Keyword('b'), 2);
        $s[new Keyword('c')];
    }

    public function test_count(): void
    {
        $s = FakeStruct::fromKVs(new Keyword('a'), 1, new Keyword('b'), 2);
        self::assertEquals(2, count($s));
    }

    public function test_equals_other_type(): void
    {
        $s = FakeStruct::fromKVs(new Keyword('a'), 1, new Keyword('b'), 2);
        $map = PersistentHashMap::fromArray(
            TypeFactory::getInstance()->getHasher(),
            TypeFactory::getInstance()->getEqualizer(),
            [new Keyword('a'), 1, new Keyword('b'), 2]
        );

        self::assertFalse($s->equals($map));
    }

    public function test_equals_different_values(): void
    {
        $s1 = FakeStruct::fromKVs(new Keyword('a'), 1, new Keyword('b'), 2);
        $s2 = FakeStruct::fromKVs(new Keyword('a'), 1, new Keyword('b'), 3);

        self::assertFalse($s1->equals($s2));
        self::assertFalse($s2->equals($s1));
    }

    public function test_equals_same_values(): void
    {
        $s1 = FakeStruct::fromKVs(new Keyword('a'), 1, new Keyword('b'), 2);
        $s2 = FakeStruct::fromKVs(new Keyword('a'), 1, new Keyword('b'), 2);

        self::assertTrue($s1->equals($s2));
        self::assertTrue($s2->equals($s1));
    }

    public function test_allowed_keys(): void
    {
        $s = FakeStruct::fromKVs(new Keyword('a'), 1, new Keyword('b'), 2);

        self::assertEquals([new Keyword('a'), new Keyword('b')], $s->getAllowedKeys());
    }

    public function test_with_meta(): void
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
