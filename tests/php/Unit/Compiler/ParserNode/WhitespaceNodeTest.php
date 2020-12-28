<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler;

use Phel\Lang\SourceLocation;
use Phel\Compiler\ParserNode\WhitespaceNode;
use PHPUnit\Framework\TestCase;

final class WhitespaceNodeTest extends TestCase
{
    public function testGetCode()
    {
        self::assertEquals(
            ' ',
            (new WhitespaceNode(' ', $this->loc(1, 0), $this->loc(1, 1)))->getCode()
        );
    }

    public function testGetStartLocation()
    {
        self::assertEquals(
            $this->loc(1, 0),
            (new WhitespaceNode(' ', $this->loc(1, 0), $this->loc(1, 1)))->getStartLocation()
        );
    }

    public function testGetEndLocation()
    {
        self::assertEquals(
            $this->loc(1, 1),
            (new WhitespaceNode(' ', $this->loc(1, 0), $this->loc(1, 1)))->getEndLocation()
        );
    }

    private function loc($line, $column): SourceLocation
    {
        return new SourceLocation('string', $line, $column);
    }
}
