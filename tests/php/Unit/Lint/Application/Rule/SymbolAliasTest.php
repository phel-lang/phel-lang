<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Application\Rule;

use Phel\Lang\Symbol;
use Phel\Lint\Application\Rule\SymbolAlias;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SymbolAliasTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function names(): iterable
    {
        yield 'dotted' => ['phel.string', 'string'];
        yield 'backslashed' => [Symbol::class, 'Symbol'];
        yield 'mixed separators' => ['app.core\\Thing', 'Thing'];
        yield 'no separator' => ['str', 'str'];
        yield 'trailing separator' => ['app.', ''];
        yield 'empty' => ['', ''];
    }

    #[DataProvider('names')]
    public function test_last_segment(string $name, string $expected): void
    {
        self::assertSame($expected, SymbolAlias::lastSegment($name));
    }
}
