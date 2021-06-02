<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use Phel\Lang\SourceLocation;
use PHPUnit\Framework\TestCase;

final class SourceLocationTest extends TestCase
{
    public function test_get_file(): void
    {
        $s = new SourceLocation('/test', 1, 2);
        $this->assertEquals('/test', $s->getFile());
    }

    public function test_set_file(): void
    {
        $s = new SourceLocation('/test', 1, 2);
        $s->setFile('/abc');
        $this->assertEquals('/abc', $s->getFile());
    }

    public function test_get_line(): void
    {
        $s = new SourceLocation('/test', 1, 2);
        $this->assertEquals(1, $s->getLine());
    }

    public function test_set_line(): void
    {
        $s = new SourceLocation('/test', 1, 2);
        $s->setLine(32);
        $this->assertEquals(32, $s->getLine());
    }

    public function test_get_column(): void
    {
        $s = new SourceLocation('/test', 1, 2);
        $this->assertEquals(2, $s->getColumn());
    }

    public function test_set_column(): void
    {
        $s = new SourceLocation('/test', 1, 2);
        $s->setColumn(32);
        $this->assertEquals(32, $s->getColumn());
    }
}
