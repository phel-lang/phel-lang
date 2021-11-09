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
        $s = FakeStruct::fromKVs(Keyword::create('a'), 1, Keyword::create('b'), 2);
        $s = $s->put(Keyword::create('a'), 3);
        self::assertEquals([Keyword::create('a'), 3, Keyword::create('b'), 2], $this->toKeyValueList($s));
    }

    public function test_put_invalid_key(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $s = FakeStruct::fromKVs(Keyword::create('a'), 1, Keyword::create('b'), 2);
        $s = $s->put(Keyword::create('c'), 2);
    }

    public function test_offset_exists(): void
    {
        $s = FakeStruct::fromKVs(Keyword::create('a'), 1, Keyword::create('b'), 2);
        self::assertTrue(isset($s[Keyword::create('a')]));
    }

    public function test_offset_exists_invalid_key(): void
    {
        $s = FakeStruct::fromKVs(Keyword::create('a'), 1, Keyword::create('b'), 2);
        self::assertFalse(isset($s[Keyword::create('c')]));
    }

    public function test_remove(): void
    {
        $s = FakeStruct::fromKVs(Keyword::create('a'), 1, Keyword::create('b'), 2);
        $s = $s->remove(Keyword::create('a'));
        self::assertEquals(FakeStruct::fromKVs(Keyword::create('b'), 2), $s);
    }

    public function test_remove_invalid_key(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $s = FakeStruct::fromKVs(Keyword::create('a'), 1, Keyword::create('b'), 2);
        $s->remove(Keyword::create('c'));
    }

    public function test_offset_get(): void
    {
        $s = FakeStruct::fromKVs(Keyword::create('a'), 1, Keyword::create('b'), 2);
        self::assertEquals(1, $s[Keyword::create('a')]);
    }

    public function test_offset_get_invalid_key(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $s = FakeStruct::fromKVs(Keyword::create('a'), 1, Keyword::create('b'), 2);
        $s[Keyword::create('c')];
    }

    public function test_count(): void
    {
        $s = FakeStruct::fromKVs(Keyword::create('a'), 1, Keyword::create('b'), 2);
        self::assertEquals(2, count($s));
    }

    public function test_equals_other_type(): void
    {
        $s = FakeStruct::fromKVs(Keyword::create('a'), 1, Keyword::create('b'), 2);
        $map = PersistentHashMap::fromArray(
            TypeFactory::getInstance()->getHasher(),
            TypeFactory::getInstance()->getEqualizer(),
            [Keyword::create('a'), 1, Keyword::create('b'), 2]
        );

        self::assertFalse($s->equals($map));
    }

    public function test_equals_different_values(): void
    {
        $s1 = FakeStruct::fromKVs(Keyword::create('a'), 1, Keyword::create('b'), 2);
        $s2 = FakeStruct::fromKVs(Keyword::create('a'), 1, Keyword::create('b'), 3);

        self::assertFalse($s1->equals($s2));
        self::assertFalse($s2->equals($s1));
    }

    public function test_equals_same_values(): void
    {
        $s1 = FakeStruct::fromKVs(Keyword::create('a'), 1, Keyword::create('b'), 2);
        $s2 = FakeStruct::fromKVs(Keyword::create('a'), 1, Keyword::create('b'), 2);

        self::assertTrue($s1->equals($s2));
        self::assertTrue($s2->equals($s1));
    }

    public function test_allowed_keys(): void
    {
        $s = FakeStruct::fromKVs(Keyword::create('a'), 1, Keyword::create('b'), 2);

        self::assertEquals([Keyword::create('a'), Keyword::create('b')], $s->getAllowedKeys());
    }

    public function test_with_meta(): void
    {
        $meta = TypeFactory::getInstance()->emptyPersistentMap();
        $s = FakeStruct::fromKVs(Keyword::create('a'), 1, Keyword::create('b'), 2);
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
