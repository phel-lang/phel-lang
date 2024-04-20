<?php

declare(strict_types=1);

namespace PhelTest\Unit\Transpiler\Parser\ParserNode;

use Phel\Lang\SourceLocation;
use Phel\Transpiler\Domain\Parser\ParserNode\NewlineNode;
use PHPUnit\Framework\TestCase;

final class NewlineNodeTest extends TestCase
{
    public function test_get_code(): void
    {
        self::assertSame(
            '\n',
            (new NewlineNode('\n', $this->loc(1, 0), $this->loc(2, 0)))->getCode(),
        );
    }

    public function test_get_start_location(): void
    {
        self::assertEquals(
            $this->loc(1, 0),
            (new NewlineNode('\n', $this->loc(1, 0), $this->loc(2, 0)))->getStartLocation(),
        );
    }

    public function test_get_end_location(): void
    {
        self::assertEquals(
            $this->loc(2, 0),
            (new NewlineNode('\n', $this->loc(1, 0), $this->loc(2, 0)))->getEndLocation(),
        );
    }

    private function loc(int $line, int $column): SourceLocation
    {
        return new SourceLocation('string', $line, $column);
    }
}
