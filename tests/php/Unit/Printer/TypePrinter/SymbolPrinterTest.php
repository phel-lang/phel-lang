<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Phel\Lang\Symbol;
use Phel\Printer\TypePrinter\SymbolPrinter;
use PHPUnit\Framework\TestCase;

final class SymbolPrinterTest extends TestCase
{
    /**
     * @dataProvider printerDataProvider
     */
    public function testPrint(string $expected, Symbol $symbol): void
    {
        self::assertSame(
            $expected,
            (new SymbolPrinter())->print($symbol)
        );
    }

    public function printerDataProvider(): \Generator
    {
        yield [
            'expected' => 'name',
            'symbol' => Symbol::createForNamespace('ns/test', 'name'),
        ];
        yield [
            'expected' => 'name',
            'symbol' => Symbol::createForNamespace(null, 'name'),
        ];
        yield [
            'expected' => 'name',
            'symbol' => Symbol::create('name'),
        ];
        yield [
            'expected' => '',
            'symbol' => Symbol::create('\\?#__\|\/'),
        ];
    }
}
