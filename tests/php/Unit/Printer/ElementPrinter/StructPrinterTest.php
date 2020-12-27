<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\ElementPrinter;

use Phel\Lang\Struct;
use Phel\Printer\ElementPrinter\StructPrinter;
use Phel\Printer\Printer;
use PHPUnit\Framework\TestCase;

final class StructPrinterTest extends TestCase
{
    /**
     * @dataProvider printerDataProvider
     */
    public function testPrint(string $actual, Struct $struct): void
    {
        self::assertSame(
            $actual,
            (new StructPrinter(Printer::readable()))->print($struct)
        );
    }

    public function printerDataProvider(): \Generator
    {
        yield [
            'actual' => '(PhelTest\Unit\Printer\ElementPrinter\StubStruct )',
            'struct' => new StubStruct([]),
        ];
        yield [
            'actual' => '(PhelTest\Unit\Printer\ElementPrinter\StubStruct nil nil)',
            'struct' => new StubStruct(['a', 'b']),
        ];
    }
}
