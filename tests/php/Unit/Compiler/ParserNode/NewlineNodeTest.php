<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler;

use Phel\Lang\SourceLocation;
use Phel\Compiler\ParserNode\NewlineNode;
use PHPUnit\Framework\TestCase;

final class NewlineNodeTest extends TestCase
{
    public function testGetCode()
    {
        self::assertEquals(
            '\n',
            (new NewlineNode('\n', $this->loc(1, 0), $this->loc(2, 0)))->getCode()
        );
    }

    public function testGetStartLocation()
    {
        self::assertEquals(
            $this->loc(1, 0),
            (new NewlineNode('\n', $this->loc(1, 0), $this->loc(2, 0)))->getStartLocation()
        );
    }

    public function testGetEndLocation()
    {
        self::assertEquals(
            $this->loc(2, 0),
            (new NewlineNode('\n', $this->loc(1, 0), $this->loc(2, 0)))->getEndLocation()
        );
    }

    private function loc($line, $column): SourceLocation
    {
        return new SourceLocation('string', $line, $column);
    }
}
