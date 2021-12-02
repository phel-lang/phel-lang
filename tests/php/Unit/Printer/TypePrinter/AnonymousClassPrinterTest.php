<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Phel\Printer\TypePrinter\AnonymousClassPrinter;
use PHPUnit\Framework\TestCase;

final class AnonymousClassPrinterTest extends TestCase
{
    public function test_print(): void
    {
        $class = new class () {
        };

        self::assertSame(
            '<PHP-AnonymousClass>',
            (new AnonymousClassPrinter())->print($class)
        );
    }
}
