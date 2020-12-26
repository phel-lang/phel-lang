<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\ElementPrinter;

use Phel\Printer\ElementPrinter\BooleanPrinter;
use PHPUnit\Framework\TestCase;

final class BooleanPrinterTest extends TestCase
{
    /**
     * @dataProvider printerDataProvider
     */
    public function testPrint(string $expected, bool $actual): void
    {
        self::assertSame(
            $expected,
            (new BooleanPrinter())->print($actual)
        );
    }

    public function printerDataProvider(): \Generator
    {
        yield [$expected = 'true', $actual = true];
        yield [$expected = 'false', $actual = false];
    }
}
