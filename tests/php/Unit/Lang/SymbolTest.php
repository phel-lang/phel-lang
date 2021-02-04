<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class SymbolTest extends TestCase
{
    public function testCreateWithoutNs(): void
    {
        $s = Symbol::create('test');
        $this->assertEquals('test', $s->getName());
        $this->assertNull($s->getNamespace());
        $this->assertEquals('test', $s->getFullName());
    }

    public function testCreateWithNs(): void
    {
        $s = Symbol::create('namespace/test');
        $this->assertEquals('test', $s->getName());
        $this->assertEquals('namespace', $s->getNamespace());
        $this->assertEquals('namespace/test', $s->getFullName());
    }

    public function testCreateForNamespaceWithoutNs(): void
    {
        $s = Symbol::createForNamespace(null, 'test');
        $this->assertEquals('test', $s->getName());
        $this->assertNull($s->getNamespace());
        $this->assertEquals('test', $s->getFullName());
    }

    public function testCreateForNamespaceWithNs(): void
    {
        $s = Symbol::createForNamespace('namespace', 'test');
        $this->assertEquals('test', $s->getName());
        $this->assertEquals('namespace', $s->getNamespace());
        $this->assertEquals('namespace/test', $s->getFullName());
    }

    public function testToString(): void
    {
        $s = Symbol::createForNamespace('namespace', 'test');
        $this->assertEquals('test', $s->__toString());
    }

    public function testGen(): void
    {
        Symbol::resetGen();
        $this->assertEquals('__phel_1', Symbol::gen());
        $this->assertEquals('bla2', Symbol::gen('bla'));
    }

    public function testHash(): void
    {
        $s = Symbol::createForNamespace('namespace', 'test');
        $this->assertEquals('test', $s->hash());
    }

    public function testEquals(): void
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

    public function testIdentical(): void
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
}
