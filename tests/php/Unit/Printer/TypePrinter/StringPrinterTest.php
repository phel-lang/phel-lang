<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Phel\Printer\TypePrinter\StringPrinter;
use PHPUnit\Framework\TestCase;

final class StringPrinterTest extends TestCase
{
    public function test_print_non_readable(): void
    {
        self::assertSame('str', StringPrinter::nonReadable()->print('str'));
    }

    public function test_print_with_color(): void
    {
        self::assertSame('[0;95mstr[0m', StringPrinter::nonReadable(withColor: true)->print('str'));
    }

    public function test_print_str(): void
    {
        self::assertSame('"str"', StringPrinter::readable()->print('str'));
    }

    public function test_mark_110(): void
    {
        self::assertSame('"\u{0100}"', StringPrinter::readable()->print("\u{100}"));
    }

    public function test_mark_1110(): void
    {
        self::assertSame('"\u{1100}"', StringPrinter::readable()->print("\u{1100}"));
    }

    public function test_mark_11110(): void
    {
        self::assertSame('"\u{11110}"', StringPrinter::readable()->print("\u{11110}"));
    }
}
