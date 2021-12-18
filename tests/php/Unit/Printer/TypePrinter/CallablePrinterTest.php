<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Phel\Printer\TypePrinter\CallablePrinter;
use PHPUnit\Framework\TestCase;

final class CallablePrinterTest extends TestCase
{
    public function test_print(): void
    {
        $class = new class () {
            public function __invoke(): string
            {
                return 'invoke method';
            }
        };

        self::assertSame('<function>', (new CallablePrinter())->print($class));
    }
}
