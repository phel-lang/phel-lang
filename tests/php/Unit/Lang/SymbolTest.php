<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class SymbolTest extends TestCase
{
    public function test_create_without_ns(): void
    {
        $s = Symbol::create('test');
        $this->assertSame('test', $s->getName());
        $this->assertNull($s->getNamespace());
        $this->assertSame('test', $s->getFullName());
    }

    public function test_create_with_ns(): void
    {
        $s = Symbol::create('namespace/test');
        $this->assertSame('test', $s->getName());
        $this->assertSame('namespace', $s->getNamespace());
        $this->assertSame('namespace/test', $s->getFullName());
    }

    public function test_create_for_namespace_without_ns(): void
    {
        $s = Symbol::createForNamespace(null, 'test');
        $this->assertSame('test', $s->getName());
        $this->assertNull($s->getNamespace());
        $this->assertSame('test', $s->getFullName());
    }

    public function test_create_for_namespace_with_ns(): void
    {
        $s = Symbol::createForNamespace('namespace', 'test');
        $this->assertSame('test', $s->getName());
        $this->assertSame('namespace', $s->getNamespace());
        $this->assertSame('namespace/test', $s->getFullName());
    }

    public function test_to_string(): void
    {
        $s = Symbol::createForNamespace('namespace', 'test');
        $this->assertSame('namespace/test', $s->__toString());
    }

    public function test_to_string_without_namespace(): void
    {
        $s = Symbol::create('test');
        $this->assertSame('test', $s->__toString());
    }

    public function test_gen(): void
    {
        Symbol::resetGen();
        $this->assertSame('__phel_1', (string) Symbol::gen());
        $this->assertSame('bla2', (string) Symbol::gen('bla'));
    }

    public function test_hash(): void
    {
        $s = Symbol::createForNamespace('namespace', 'test');
        $this->assertSame(crc32('test'), $s->hash());
    }

    public function test_equals(): void
    {
        $s1 = Symbol::createForNamespace('namespace', 'test');
        $s2 = Symbol::createForNamespace('namespace', 'test');
        $s3 = Symbol::createForNamespace('namespace', 'abc');
        $s4 = Symbol::createForNamespace('abc', 'test');

        $this->assertTrue($s1->equals($s2));
        $this->assertTrue($s2->equals($s1));
        $this->assertFalse($s1->equals($s3));
        $this->assertFalse($s3->equals($s1));
        $this->assertFalse($s1->equals($s4));
        $this->assertFalse($s4->equals($s1));
    }

    public function test_identical(): void
    {
        $s1 = Symbol::createForNamespace('namespace', 'test');
        $s2 = Symbol::createForNamespace('namespace', 'test');
        $s3 = Symbol::createForNamespace('namespace', 'abc');
        $s4 = Symbol::createForNamespace('abc', 'test');

        $this->assertTrue($s1->identical($s2));
        $this->assertTrue($s2->identical($s1));
        $this->assertFalse($s1->identical($s3));
        $this->assertFalse($s3->identical($s1));
        $this->assertFalse($s1->identical($s4));
        $this->assertFalse($s4->identical($s1));
    }

    public function test_equals_same_instance(): void
    {
        $s = Symbol::createForNamespace('namespace', 'test');

        $this->assertTrue($s->equals($s));
        $this->assertTrue($s->identical($s));
    }

    public function test_equals_distinct_value_equal_instances(): void
    {
        $s1 = Symbol::createForNamespace('namespace', 'test');
        $s2 = Symbol::createForNamespace('namespace', 'test');

        $this->assertNotSame($s1, $s2);
        $this->assertTrue($s1->equals($s2));
        $this->assertTrue($s2->equals($s1));
    }

    public function test_equals_differing_name(): void
    {
        $s1 = Symbol::createForNamespace('namespace', 'test');
        $s2 = Symbol::createForNamespace('namespace', 'other');

        $this->assertFalse($s1->equals($s2));
        $this->assertFalse($s2->equals($s1));
    }

    public function test_equals_differing_namespace(): void
    {
        $s1 = Symbol::createForNamespace('namespace', 'test');
        $s2 = Symbol::createForNamespace('other', 'test');

        $this->assertFalse($s1->equals($s2));
        $this->assertFalse($s2->equals($s1));
    }

    public function test_equals_null_namespace_vs_set_namespace(): void
    {
        $s1 = Symbol::createForNamespace(null, 'test');
        $s2 = Symbol::createForNamespace('namespace', 'test');

        $this->assertFalse($s1->equals($s2));
        $this->assertFalse($s2->equals($s1));
    }

    public function test_equals_non_symbol_argument(): void
    {
        $s = Symbol::createForNamespace('namespace', 'test');

        $this->assertFalse($s->equals('namespace/test'));
        $this->assertFalse($s->equals(null));
    }

    public function test_value_equal_symbols_keep_independent_source_locations(): void
    {
        $s1 = Symbol::createForNamespace('namespace', 'test');
        $s2 = Symbol::createForNamespace('namespace', 'test');

        $s1->setStartLocation(new SourceLocation('a.phel', 1, 0));
        $s2->setStartLocation(new SourceLocation('b.phel', 2, 4));

        $this->assertTrue($s1->equals($s2));
        $this->assertNotSame($s1->getStartLocation(), $s2->getStartLocation());
        $this->assertSame('a.phel', $s1->getStartLocation()?->getFile());
        $this->assertSame('b.phel', $s2->getStartLocation()?->getFile());
    }
}
