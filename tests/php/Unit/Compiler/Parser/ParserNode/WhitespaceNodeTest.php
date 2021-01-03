<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Parser\ParserNode;

use Phel\Compiler\Parser\ParserNode\WhitespaceNode;
use Phel\Lang\SourceLocation;
use PHPUnit\Framework\TestCase;

final class WhitespaceNodeTest extends TestCase
{
    public function testGetCode(): void
    {
        self::assertEquals(
            ' ',
            (new WhitespaceNode(' ', $this->loc(1, 0), $this->loc(1, 1)))->getCode()
        );
    }

    public function testGetStartLocation(): void
    {
        self::assertEquals(
            $this->loc(1, 0),
            (new WhitespaceNode(' ', $this->loc(1, 0), $this->loc(1, 1)))->getStartLocation()
        );
    }

    public function testGetEndLocation(): void
    {
        self::assertEquals(
            $this->loc(1, 1),
            (new WhitespaceNode(' ', $this->loc(1, 0), $this->loc(1, 1)))->getEndLocation()
        );
    }

    private function loc(int $line, int $column): SourceLocation
    {
        return new SourceLocation('string', $line, $column);
    }
}
