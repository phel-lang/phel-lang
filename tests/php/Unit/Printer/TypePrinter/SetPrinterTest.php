<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Generator;
use Phel\Lang\Set;
use Phel\Printer\Printer;
use Phel\Printer\TypePrinter\SetPrinter;
use PHPUnit\Framework\TestCase;

final class SetPrinterTest extends TestCase
{
    /**
     * @dataProvider printerDataProvider
     */
    public function testPrint(string $expected, Set $set): void
    {
        self::assertSame(
            $expected,
            (new SetPrinter(Printer::readable()))->print($set)
        );
    }

    public function printerDataProvider(): Generator
    {
        yield 'empty set' => [
            'expected' => '(set)',
            'set' => new Set([]),
        ];

        yield 'set with one value' => [
            'expected' => '(set "name")',
            'set' => new Set(['name']),
        ];

        yield 'set with multiple values' => [
            'expected' => '(set "key1" "key2")',
            'set' => new Set(['key1', 'key2']),
        ];
    }
}
