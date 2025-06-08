<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Generator;
use Phel\Lang\Symbol;
use Phel\Printer\TypePrinter\SymbolPrinter;
use PHPUnit\Framework\TestCase;

final class SymbolPrinterTest extends TestCase
{
    /**
     * @dataProvider printerDataProvider
     */
    public function test_print(string $expected, Symbol $symbol): void
    {
        self::assertSame(
            $expected,
            (new SymbolPrinter())->print($symbol),
        );
    }

    public static function printerDataProvider(): Generator
    {
        yield 'symbol with namespace' => [
            'name',
            Symbol::createForNamespace('ns/test', 'name'),
        ];

        yield 'symbol without namespace explicit' => [
            'name',
            Symbol::createForNamespace(null, 'name'),
        ];

        yield 'symbol with namespace explicit' => [
            '\/?#__\|',
            Symbol::create('ns/\\/?#__\|'),
        ];

        yield 'symbol without namespace implicit' => [
            'name',
            Symbol::create('name'),
        ];
    }
}
