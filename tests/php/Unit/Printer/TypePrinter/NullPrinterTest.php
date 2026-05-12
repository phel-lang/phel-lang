<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Phel\Shared\Printer\TypePrinter\NullPrinter;
use PHPUnit\Framework\TestCase;

final class NullPrinterTest extends TestCase
{
    public function test_print(): void
    {
        self::assertSame('nil', new NullPrinter()->print(null));
    }
}
