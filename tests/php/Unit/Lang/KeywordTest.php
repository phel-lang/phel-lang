<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use Phel\Lang\Keyword;
use Phel\Lang\TypeFactory;
use PHPUnit\Framework\TestCase;

final class KeywordTest extends TestCase
{
    public function test_get_name(): void
    {
        $keyword = new Keyword('test');
        $this->assertEquals('test', $keyword->getName());
    }

    public function test_get_hash(): void
    {
        $keyword = new Keyword('test');
        $this->assertEquals(crc32(':test'), $keyword->hash());
    }

    public function test_equals(): void
    {
        $keyword1 = new Keyword('test');
        $keyword2 = new Keyword('test');
        $this->assertTrue($keyword1->equals($keyword1));
        $this->assertTrue($keyword1->equals($keyword2));
        $this->assertTrue($keyword2->equals($keyword1));
    }

    public function test_not_equals(): void
    {
        $keyword1 = new Keyword('test');
        $keyword2 = new Keyword('test1');
        $this->assertFalse($keyword1->equals($keyword2));
        $this->assertFalse($keyword2->equals($keyword1));
        $this->assertFalse($keyword1->equals('test'));
        $this->assertFalse($keyword1->equals(':test'));
    }

    public function test_identical(): void
    {
        $keyword1 = new Keyword('test');
        $keyword2 = new Keyword('test');
        $this->assertTrue($keyword1->identical($keyword1));
        $this->assertTrue($keyword1->identical($keyword2));
        $this->assertTrue($keyword2->identical($keyword1));
    }

    public function test_to_string(): void
    {
        $keyword = new Keyword('test');
        $this->assertEquals(':test', $keyword->__toString());
    }

    public function test_invoke(): void
    {
        $keyword1 = new Keyword('test1');
        $keyword2 = new Keyword('test2');
        $table = TypeFactory::getInstance()->persistentMapFromKVs(new Keyword('test1'), 'abc');
        $this->assertEquals('abc', $keyword1($table));
        $this->assertNull($keyword2($table));
        $this->assertEquals('xyz', $keyword2($table, 'xyz'));
    }
}
