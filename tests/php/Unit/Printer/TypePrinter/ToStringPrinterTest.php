<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Phel\Printer\TypePrinter\ToStringPrinter;
use PHPUnit\Framework\TestCase;

final class ToStringPrinterTest extends TestCase
{
    public function test_print(): void
    {
        $class = new class () {
            public function __toString(): string
            {
                return 'toString method';
            }
        };

        self::assertSame('toString method', (new ToStringPrinter())->print($class));
    }
}
