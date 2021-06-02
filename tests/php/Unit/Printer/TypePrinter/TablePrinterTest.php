<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Generator;
use Phel\Lang\Table;
use Phel\Printer\Printer;
use Phel\Printer\TypePrinter\TablePrinter;
use PHPUnit\Framework\TestCase;

final class TablePrinterTest extends TestCase
{
    /**
     * @dataProvider printerDataProvider
     */
    public function test_print(string $expected, Table $table): void
    {
        self::assertSame(
            $expected,
            (new TablePrinter(Printer::readable()))->print($table)
        );
    }

    public function printerDataProvider(): Generator
    {
        yield 'empty table' => [
            'expected' => '@{}',
            'table' => Table::empty(),
        ];

        yield 'empty table KVArray' => [
            'expected' => '@{}',
            'table' => Table::fromKVArray([]),
        ];

        yield 'table from KVArray' => [
            'expected' => '@{"a" 1 "b" 2}',
            'table' => Table::fromKVArray(['a', 1, 'b', 2]),
        ];

        yield 'table from KV values' => [
            'expected' => '@{"a" 1 "b" 2}',
            'table' => Table::fromKVs('a', 1, 'b', 2),
        ];
    }
}
