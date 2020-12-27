<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Phel\Lang\Table;
use Phel\Printer\TypePrinter\TablePrinter;
use Phel\Printer\Printer;
use PHPUnit\Framework\TestCase;

final class TablePrinterTest extends TestCase
{
    /**
     * @dataProvider printerDataProvider
     */
    public function testPrint(string $expected, Table $table): void
    {
        self::assertSame(
            $expected,
            (new TablePrinter(Printer::readable()))->print($table)
        );
    }

    public function printerDataProvider(): \Generator
    {
        yield [
            'expected' => '@{}',
            'table' => Table::empty(),
        ];
        yield [
            'expected' => '@{}',
            'table' => Table::fromKVArray([]),
        ];
        yield [
            'expected' => '@{"a" 1 "b" 2}',
            'table' => Table::fromKVArray(['a', 1, 'b', 2]),
        ];
        yield [
            'expected' => '@{"a" 1 "b" 2}',
            'table' => Table::fromKVs('a', 1, 'b', 2),
        ];
    }
}
