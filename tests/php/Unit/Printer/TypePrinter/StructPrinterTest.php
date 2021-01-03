<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Generator;
use Phel\Lang\AbstractStruct;
use Phel\Printer\TypePrinter\StructPrinter;
use Phel\Printer\Printer;
use PHPUnit\Framework\TestCase;

final class StructPrinterTest extends TestCase
{
    /**
     * @dataProvider printerDataProvider
     */
    public function testPrint(string $actual, AbstractStruct $struct): void
    {
        self::assertSame(
            $actual,
            (new StructPrinter(Printer::readable()))->print($struct)
        );
    }

    public function printerDataProvider(): Generator
    {
        yield 'empty struct' => [
            'actual' => '(PhelTest\Unit\Printer\TypePrinter\StubStruct )',
            'struct' => new StubStruct([]),
        ];

        yield 'struct with multiple values' => [
            'actual' => '(PhelTest\Unit\Printer\TypePrinter\StubStruct nil nil)',
            'struct' => new StubStruct(['a', 'b']),
        ];
    }
}
