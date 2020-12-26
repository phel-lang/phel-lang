<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\ElementPrinter;

use Phel\Printer\ElementPrinter\NullPrinter;
use PHPUnit\Framework\TestCase;

final class NullPrinterTest extends TestCase
{
    public function testPrint(): void
    {
        self::assertSame('nil', (new NullPrinter())->print(null));
    }
}
