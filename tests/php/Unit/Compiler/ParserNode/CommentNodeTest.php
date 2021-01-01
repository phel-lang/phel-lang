<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler;

use Phel\Lang\SourceLocation;
use Phel\Compiler\Parser\ParserNode\CommentNode;
use PHPUnit\Framework\TestCase;

final class CommentNodeTest extends TestCase
{
    public function testGetCode()
    {
        self::assertEquals(
            '# Test',
            (new CommentNode('# Test', $this->loc(1, 0), $this->loc(1, 6)))->getCode()
        );
    }

    public function testGetStartLocation()
    {
        self::assertEquals(
            $this->loc(1, 0),
            (new CommentNode('# Test', $this->loc(1, 0), $this->loc(1, 6)))->getStartLocation()
        );
    }

    public function testGetEndLocation()
    {
        self::assertEquals(
            $this->loc(1, 6),
            (new CommentNode('# Test', $this->loc(1, 0), $this->loc(1, 6)))->getEndLocation()
        );
    }

    private function loc($line, $column): SourceLocation
    {
        return new SourceLocation('string', $line, $column);
    }
}
