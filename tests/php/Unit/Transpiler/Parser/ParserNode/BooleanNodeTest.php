<?php

declare(strict_types=1);

namespace PhelTest\Unit\Transpiler\Parser\ParserNode;

use Phel\Lang\SourceLocation;
use Phel\Transpiler\Domain\Parser\ParserNode\BooleanNode;
use PHPUnit\Framework\TestCase;

final class BooleanNodeTest extends TestCase
{
    public function test_get_code(): void
    {
        self::assertSame(
            'true',
            (new BooleanNode('true', $this->loc(1, 0), $this->loc(1, 4), true))->getCode(),
        );
    }

    public function test_get_start_location(): void
    {
        self::assertEquals(
            $this->loc(1, 0),
            (new BooleanNode('true', $this->loc(1, 0), $this->loc(1, 4), true))->getStartLocation(),
        );
    }

    public function test_get_end_location(): void
    {
        self::assertEquals(
            $this->loc(1, 4),
            (new BooleanNode('true', $this->loc(1, 0), $this->loc(1, 4), true))->getEndLocation(),
        );
    }

    public function test_value(): void
    {
        self::assertTrue(
            (new BooleanNode('true', $this->loc(1, 0), $this->loc(1, 4), true))->getValue(),
        );
    }

    private function loc(int $line, int $column): SourceLocation
    {
        return new SourceLocation('string', $line, $column);
    }
}
