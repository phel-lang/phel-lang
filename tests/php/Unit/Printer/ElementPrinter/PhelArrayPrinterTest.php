<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\ElementPrinter;

use Phel\Lang\PhelArray;
use Phel\Printer\ElementPrinter\PhelArrayPrinter;
use Phel\Printer\Printer;
use PHPUnit\Framework\TestCase;

final class PhelArrayPrinterTest extends TestCase
{
    /**
     * @dataProvider printerDataProvider
     */
    public function testPrint(string $expected, PhelArray $phelArray): void
    {
        self::assertSame(
            $expected,
            (new PhelArrayPrinter(Printer::readable()))->print($phelArray)
        );
    }

    public function printerDataProvider(): \Generator
    {
        yield [
            'expected' => '@["name"]',
            'phelArray' => new PhelArray(['name'])
        ];
        yield [
            'expected' => '@["\\\?#__\\\|\\\/"]',
            'phelArray' => new PhelArray(['\\?#__\|\/'])
        ];
    }
}
