<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Parser\ParserNode;

use Phel\Compiler\Parser\ParserNode\CommentNode;
use Phel\Lang\SourceLocation;
use PHPUnit\Framework\TestCase;

final class CommentNodeTest extends TestCase
{
    public function test_get_code(): void
    {
        self::assertEquals(
            '# Test',
            (new CommentNode('# Test', $this->loc(1, 0), $this->loc(1, 6)))->getCode()
        );
    }

    public function test_get_start_location(): void
    {
        self::assertEquals(
            $this->loc(1, 0),
            (new CommentNode('# Test', $this->loc(1, 0), $this->loc(1, 6)))->getStartLocation()
        );
    }

    public function test_get_end_location(): void
    {
        self::assertEquals(
            $this->loc(1, 6),
            (new CommentNode('# Test', $this->loc(1, 0), $this->loc(1, 6)))->getEndLocation()
        );
    }

    private function loc(int $line, int $column): SourceLocation
    {
        return new SourceLocation('string', $line, $column);
    }
}
