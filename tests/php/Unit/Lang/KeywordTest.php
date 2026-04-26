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

    public function test_interned_same_instance(): void
    {
        $keyword1 = Keyword::create('interned');
        $keyword2 = Keyword::create('interned');
        $this->assertSame($keyword1, $keyword2);
    }

    public function test_interned_same_instance_with_namespace(): void
    {
        $keyword1 = Keyword::create('interned', 'ns');
        $keyword2 = Keyword::create('interned', 'ns');
        $this->assertSame($keyword1, $keyword2);
    }

    public function test_interned_different_instances_for_different_names(): void
    {
        $keyword1 = Keyword::create('a');
        $keyword2 = Keyword::create('b');
        $this->assertNotSame($keyword1, $keyword2);
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

    public function test_invoke_returns_null_for_present_null_value_with_default(): void
    {
        $keyword = Keyword::create('a');
        $table = Phel::map($keyword, null);

        $this->assertNull($keyword($table, 'fallback'));
    }

    public function test_invoke_on_set_returns_keyword_when_present(): void
    {
        $keyword1 = Keyword::create('test1');
        $keyword2 = Keyword::create('test2');
        $set = Phel::set([$keyword1]);

        $this->assertSame($keyword1, $keyword1($set));
        $this->assertNull($keyword2($set));
        $this->assertSame('fallback', $keyword2($set, 'fallback'));
    }

    public function test_invoke_on_nil_returns_default(): void
    {
        $keyword = Keyword::create('missing');
        $this->assertNull($keyword(null));
        $this->assertSame('fallback', $keyword(null, 'fallback'));
    }
}
