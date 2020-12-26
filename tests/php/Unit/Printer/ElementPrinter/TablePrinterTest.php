<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\ElementPrinter;

use Phel\Lang\Table;
use Phel\Printer\ElementPrinter\TablePrinter;
use Phel\Printer\Printer;
use PHPUnit\Framework\TestCase;

final class TablePrinterTest extends TestCase
{
    /**
     * @dataProvider printerDataProvider
     */
    public function testPrint(string $expected, Table $actual): void
    {
        self::assertSame(
            $expected,
            (new TablePrinter(Printer::readable()))->print($actual)
        );
    }

    public function printerDataProvider(): \Generator
    {
        yield [$expected = '@{}', $actual = Table::empty()];
        yield [$expected = '@{}', $actual = Table::fromKVArray([])];
        yield [$expected = '@{"a" 1 "b" 2}', $actual = Table::fromKVArray(['a', 1, 'b', 2])];
        yield [$expected = '@{"a" 1 "b" 2}', $actual = Table::fromKVs('a', 1, 'b', 2)];
    }
}
