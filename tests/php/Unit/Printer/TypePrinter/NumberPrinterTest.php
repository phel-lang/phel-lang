<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Generator;
use Phel\Printer\TypePrinter\NumberPrinter;
use PHPUnit\Framework\TestCase;

final class NumberPrinterTest extends TestCase
{
    /**
     * @dataProvider printerDataProvider
     *
     * @param int|float $number
     */
    public function test_print(string $expected, $number): void
    {
        self::assertSame(
            $expected,
            (new NumberPrinter())->print($number)
        );
    }

    public function printerDataProvider(): Generator
    {
        yield 'integer' => [
            'expected' => '1',
            'number' => 1,
        ];

        yield 'float' => [
            'expected' => '1.02',
            'number' => 1.02,
        ];
    }
}
