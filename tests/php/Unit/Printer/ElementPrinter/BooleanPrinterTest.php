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
    public function testPrint(string $expected, bool $boolean): void
    {
        self::assertSame(
            $expected,
            (new BooleanPrinter())->print($boolean)
        );
    }

    public function printerDataProvider(): \Generator
    {
        yield [
            'expected' => 'true',
            'boolean' => true
        ];
        yield [
            'expected' => 'false',
            'boolean' => false
        ];
    }
}
