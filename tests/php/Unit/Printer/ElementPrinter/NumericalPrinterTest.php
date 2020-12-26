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
     * @param int|float $actual
     */
    public function testPrint(string $expected, $actual): void
    {
        self::assertSame(
            $expected,
            (new NumericalPrinter())->print($actual)
        );
    }

    public function printerDataProvider(): \Generator
    {
        yield [$expected = '1', $actual = 1];
        yield [$expected = '1.02', $actual = 1.02];
    }
}
