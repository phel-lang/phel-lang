<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler;

use Phel\Lang\SourceLocation;
use Phel\Compiler\Parser\ParserNode\BooleanNodeAbstract;
use PHPUnit\Framework\TestCase;

final class BooleanNodeTest extends TestCase
{
    public function testGetCode()
    {
        self::assertEquals(
            'true',
            (new BooleanNodeAbstract('true', $this->loc(1, 0), $this->loc(1, 4), true))->getCode()
        );
    }

    public function testGetStartLocation()
    {
        self::assertEquals(
            $this->loc(1, 0),
            (new BooleanNodeAbstract('true', $this->loc(1, 0), $this->loc(1, 4), true))->getStartLocation()
        );
    }

    public function testGetEndLocation()
    {
        self::assertEquals(
            $this->loc(1, 4),
            (new BooleanNodeAbstract('true', $this->loc(1, 0), $this->loc(1, 4), true))->getEndLocation()
        );
    }

    public function testValue()
    {
        self::assertTrue(
            (new BooleanNodeAbstract('true', $this->loc(1, 0), $this->loc(1, 4), true))->getValue()
        );
    }

    private function loc($line, $column): SourceLocation
    {
        return new SourceLocation('string', $line, $column);
    }
}
