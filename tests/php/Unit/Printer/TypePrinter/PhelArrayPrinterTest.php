<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Generator;
use Phel\Lang\PhelArray;
use Phel\Printer\Printer;
use Phel\Printer\TypePrinter\PhelArrayPrinter;
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

    public function printerDataProvider(): Generator
    {
        yield 'string name' => [
            'expected' => '@["name"]',
            'phelArray' => new PhelArray(['name']),
        ];

        yield 'special chars string' => [
            'expected' => '@["\\\?#__\\\|\\\/"]',
            'phelArray' => new PhelArray(['\\?#__\|\/']),
        ];
    }
}
