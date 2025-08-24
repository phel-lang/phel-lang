<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use Phel;
use Phel\Lang\Keyword;
use PHPUnit\Framework\TestCase;

final class KeywordTest extends TestCase
{
    public function test_get_name(): void
    {
        $keyword = Keyword::create('test');
        $this->assertSame('test', $keyword->getName());
    }

    public function test_get_hash(): void
    {
        $keyword = Keyword::create('test');
        $this->assertSame(crc32(':test'), $keyword->hash());

        $keyword = Keyword::create('test', 'foo');
        $this->assertSame(crc32(':foo/test'), $keyword->hash());
    }

    public function test_get_namespace(): void
    {
        $keyword = Keyword::create('bar', 'foo');
        $this->assertSame('foo', $keyword->getNamespace());

        $keyword = Keyword::create('bar');
        $this->assertNull($keyword->getNamespace());
    }

    public function test_equals(): void
    {
        $keyword1 = Keyword::create('test');
        $keyword2 = Keyword::create('test');
        $this->assertTrue($keyword1->equals($keyword1));
        $this->assertTrue($keyword1->equals($keyword2));
        $this->assertTrue($keyword2->equals($keyword1));
    }

    public function test_equals_with_namespace(): void
    {
        $keyword1 = Keyword::create('test', 'foo');
        $keyword2 = Keyword::create('test', 'foo');
        $this->assertTrue($keyword1->equals($keyword1));
        $this->assertTrue($keyword1->equals($keyword2));
        $this->assertTrue($keyword2->equals($keyword1));
    }

    public function test_not_equals(): void
    {
        $keyword1 = Keyword::create('test');
        $keyword2 = Keyword::create('test1');
        $this->assertFalse($keyword1->equals($keyword2));
        $this->assertFalse($keyword2->equals($keyword1));
        $this->assertFalse($keyword1->equals('test'));
        $this->assertFalse($keyword1->equals(':test'));
    }

    public function test_not_equals_with_namespace(): void
    {
        $keyword1 = Keyword::create('test', 'foo');
        $keyword2 = Keyword::create('test1', 'foo');
        $keyword3 = Keyword::create('test', 'bar');
        $this->assertFalse($keyword1->equals($keyword2));
        $this->assertFalse($keyword2->equals($keyword1));
        $this->assertFalse($keyword1->equals('test'));
        $this->assertFalse($keyword1->equals(':test'));
        $this->assertFalse($keyword1->equals($keyword3));
        $this->assertFalse($keyword3->equals($keyword1));
    }

    public function test_identical(): void
    {
        $keyword1 = Keyword::create('test');
        $keyword2 = Keyword::create('test');
        $this->assertTrue($keyword1->identical($keyword1));
        $this->assertTrue($keyword1->identical($keyword2));
        $this->assertTrue($keyword2->identical($keyword1));
    }

    public function test_identical_with_namespace(): void
    {
        $keyword1 = Keyword::create('test', 'foo');
        $keyword2 = Keyword::create('test', 'foo');
        $this->assertTrue($keyword1->identical($keyword1));
        $this->assertTrue($keyword1->identical($keyword2));
        $this->assertTrue($keyword2->identical($keyword1));
    }

    public function test_to_string(): void
    {
        $keyword = Keyword::create('test');
        $this->assertSame(':test', $keyword->__toString());
    }

    public function test_invoke(): void
    {
        $keyword1 = Keyword::create('test1');
        $keyword2 = Keyword::create('test2');
        $table = Phel::map(Keyword::create('test1'), 'abc');
        $this->assertSame('abc', $keyword1($table));
        $this->assertNull($keyword2($table));
        $this->assertSame('xyz', $keyword2($table, 'xyz'));
    }
}
