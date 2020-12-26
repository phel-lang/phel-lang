<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\ElementPrinter;

use Phel\Lang\Symbol;
use Phel\Printer\ElementPrinter\SymbolPrinter;
use PHPUnit\Framework\TestCase;

final class SymbolPrinterTest extends TestCase
{
    /**
     * @dataProvider printerDataProvider
     */
    public function testPrint(string $expected, Symbol $actual): void
    {
        self::assertSame(
            $expected,
            (new SymbolPrinter())->print($actual)
        );
    }

    public function printerDataProvider(): \Generator
    {
        yield [$expected = 'name', $actual = Symbol::createForNamespace('ns/test', 'name')];
        yield [$expected = 'name', $actual = Symbol::createForNamespace(null, 'name')];
        yield [$expected = 'name', $actual = Symbol::create('name')];
        yield [$expected = '', $actual = Symbol::create('\\?#__\|\/')];
    }
}
