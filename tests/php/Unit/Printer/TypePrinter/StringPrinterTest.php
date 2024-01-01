<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Phel\Printer\TypePrinter\StringPrinter;
use PHPUnit\Framework\TestCase;

final class StringPrinterTest extends TestCase
{
    public function test_print_no_readable(): void
    {
        self::assertSame('str', (new StringPrinter(false))->print('str'));
    }

    public function test_print_str(): void
    {
        self::assertSame('"str"', (new StringPrinter(true))->print('str'));
    }

    public function test_mark_110(): void
    {
        self::assertSame('"\u{0100}"', (new StringPrinter(true))->print("\u{100}"));
    }

    public function test_mark_1110(): void
    {
        self::assertSame('"\u{1100}"', (new StringPrinter(true))->print("\u{1100}"));
    }

    public function test_mark_11110(): void
    {
        self::assertSame('"\u{11110}"', (new StringPrinter(true))->print("\u{11110}"));
    }
}
