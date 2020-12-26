<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\ElementPrinter;

use Phel\Printer\ElementPrinter\StringPrinter;
use PHPUnit\Framework\TestCase;

final class StringPrinterTest extends TestCase
{
    public function testPrintNoReadable(): void
    {
        self::assertSame('str', (new StringPrinter(false))->print('str'));
    }

    public function testPrintReadable(): void
    {
        self::assertSame('"str"', (new StringPrinter(true))->print('str'));
    }
}
