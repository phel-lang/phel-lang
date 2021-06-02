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
            (new SymbolPrinter())->print($symbol)
        );
    }

    public function printerDataProvider(): Generator
    {
        yield 'symbol with namespace' => [
            'expected' => 'name',
            'symbol' => Symbol::createForNamespace('ns/test', 'name'),
        ];

        yield 'symbol without namespace explicit' => [
            'expected' => 'name',
            'symbol' => Symbol::createForNamespace(null, 'name'),
        ];

        yield 'symbol with namespace explicit' => [
            'expected' => '\/?#__\|',
            'symbol' => Symbol::create('ns/\\/?#__\|'),
        ];

        yield 'symbol without namespace implicit' => [
            'expected' => 'name',
            'symbol' => Symbol::create('name'),
        ];
    }
}
