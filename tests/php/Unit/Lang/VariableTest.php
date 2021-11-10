<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use Phel\Lang\Variable;
use PHPUnit\Framework\TestCase;

final class VariableTest extends TestCase
{
    public function test_deref(): void
    {
        $v = new Variable(null, 10);
        $this->assertEquals(10, $v->deref());
    }

    public function test_set(): void
    {
        $v = new Variable(null, 10);
        $v->set(20);
        $this->assertEquals(20, $v->deref());
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

        $this->assertEquals(crc32(spl_object_hash($v1)), $v1->hash());
    }
}
