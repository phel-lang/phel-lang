<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Phel\Shared\Printer\TypePrinter\StringPrinter;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_readable_control_boundaries_are_escaped(): void
    {
        self::assertSame('"\\x1f ~\\x7f"', StringPrinter::readable()->print("\x1f ~\x7f"));
        self::assertSame("\x1f ~\x7f", StringPrinter::nonReadable()->print("\x1f ~\x7f"));
    }

    public function test_mark_110(): void
    {
        self::assertSame('"Ā"', StringPrinter::readable()->print("\u{100}"));
    }

    public function test_mark_1110(): void
    {
        self::assertSame('"ᄀ"', StringPrinter::readable()->print("\u{1100}"));
    }

    public function test_mark_11110(): void
    {
        self::assertSame('"𑄐"', StringPrinter::readable()->print("\u{11110}"));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformed_utf8_strings(): iterable
    {
        foreach (["\xC3", "\xE2", "\xF0", "\xE2\x82", "\xF0\x9F\x98"] as $prefix) {
            foreach (['"', '$x', '\\'] as $suffix) {
                yield bin2hex($prefix . $suffix) => [$prefix . $suffix];
            }
        }
    }

    #[DataProvider('malformed_utf8_strings')]
    public function test_malformed_utf8_keeps_special_characters_literal(string $value): void
    {
        $literal = StringPrinter::readable()->print($value);

        self::assertSame($value, eval('return ' . $literal . ';'));
    }

    public function test_non_readable_mark_110(): void
    {
        self::assertSame('"\u{0100}"', StringPrinter::nonReadable()->print('"\u{0100}"'));
    }

    public function test_non_readable_mark_1110(): void
    {
        self::assertSame('"\u{1100}"', StringPrinter::nonReadable()->print('"\u{1100}"'));
    }

    public function test_non_readable_mark_11110(): void
    {
        self::assertSame('"\u{11110}"', StringPrinter::nonReadable()->print('"\u{11110}"'));
    }
}
