<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\ElementPrinter;

use Phel\Printer\ElementPrinter\ArrayPrinter;
use PHPUnit\Framework\TestCase;

final class ArrayPrinterTest extends TestCase
{
    public function testPrint(): void
    {
        self::assertSame('<PHP-Array>', (new ArrayPrinter())->print([]));
    }
}
