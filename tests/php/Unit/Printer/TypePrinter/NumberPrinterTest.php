<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Generator;
use Phel\Printer\TypePrinter\NumberPrinter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NumberPrinterTest extends TestCase
{
    #[DataProvider('printerDataProvider')]
    public function test_print(string $expected, float|int $number): void
    {
        self::assertSame(
            $expected,
            (new NumberPrinter())->print($number),
        );
    }

    public static function printerDataProvider(): Generator
    {
        yield 'integer' => [
            '1',
            1,
        ];

        yield 'float' => [
            '1.02',
            1.02,
        ];
    }
}
