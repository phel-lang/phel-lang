<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use InvalidArgumentException;
use Phel\Lang\Variable;
use PHPUnit\Framework\TestCase;

final class VariableTest extends TestCase
{
    public function test_deref(): void
    {
        $v = new Variable(null, 10);
        $this->assertSame(10, $v->deref());
    }

    public function test_set(): void
    {
        $v = new Variable(null, 10);
        $v->set(20);
        $this->assertSame(20, $v->deref());
    }

    public function test_set_returns_new_value(): void
    {
        $v = new Variable(null, 10);
        $this->assertSame(20, $v->set(20));
    }

    public function test_set_returns_value_after_watch_runs(): void
    {
        $v = new Variable(null, 1);
        $called = false;
        $v->addWatch('w', static function () use (&$called): void {
            $called = true;
        });

        $this->assertSame(2, $v->set(2));
        $this->assertTrue($called);
    }

    public function test_set_throws_when_validator_rejects(): void
    {
        $v = new Variable(null, 1);
        $v->setValidator(static fn($value): bool => $value > 0);

        $this->expectException(InvalidArgumentException::class);
        $v->set(-1);
    }

    public function test_equals(): void
    {
        $v1 = new Variable(null, 10);
        $v2 = new Variable(null, 10);

        $this->assertTrue($v1->equals($v1));
        $this->assertFalse($v1->equals($v2));
        $this->assertFalse($v2->equals($v1));
    }

    public function test_hash(): void
    {
        $v1 = new Variable(null, 10);

        $this->assertSame(crc32(spl_object_hash($v1)), $v1->hash());
    }
}
