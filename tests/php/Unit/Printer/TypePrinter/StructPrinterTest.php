<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Generator;
use Phel\Lang\Collections\Struct\AbstractPersistentStruct;
use Phel\Lang\Keyword;
use Phel\Printer\Printer;
use Phel\Printer\TypePrinter\StructPrinter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StructPrinterTest extends TestCase
{
    #[DataProvider('printerDataProvider')]
    public function test_print(string $actual, AbstractPersistentStruct $struct): void
    {
        self::assertSame(
            $actual,
            (new StructPrinter(Printer::readable()))->print($struct),
        );
    }

    public static function printerDataProvider(): Generator
    {
        yield 'empty struct' => [
            '(PhelTest\Unit\Printer\TypePrinter\StubStruct )',
            new StubStruct([]),
        ];

        yield 'struct with multiple values' => [
            '(PhelTest\Unit\Printer\TypePrinter\StubStruct nil nil)',
            new StubStruct([Keyword::create('a'), Keyword::create('b')]),
        ];
    }
}
