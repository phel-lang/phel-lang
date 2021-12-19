<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Phel\Lang\FnInterface;
use Phel\Printer\TypePrinter\FnPrinter;
use PHPUnit\Framework\TestCase;

final class FnPrinterTest extends TestCase
{
    public function test_print(): void
    {
        $class = new class () implements FnInterface {
            public function __invoke(): string
            {
                return 'invoke method';
            }
        };

        self::assertSame('<function>', (new FnPrinter())->print($class));
    }
}
