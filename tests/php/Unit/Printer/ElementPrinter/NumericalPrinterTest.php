<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\ElementPrinter;

use Phel\Printer\ElementPrinter\NumericalPrinter;
use PHPUnit\Framework\TestCase;

final class NumericalPrinterTest extends TestCase
{
    /**
     * @dataProvider printerDataProvider
     *
     * @param int|float $number
     */
    public function testPrint(string $expected, $number): void
    {
        self::assertSame(
            $expected,
            (new NumericalPrinter())->print($number)
        );
    }

    public function printerDataProvider(): \Generator
    {
        yield [
            'expected' => '1',
            'number' => 1
        ];
        yield [
            'expected' => '1.02',
            'number' => 1.02
        ];
    }
}
