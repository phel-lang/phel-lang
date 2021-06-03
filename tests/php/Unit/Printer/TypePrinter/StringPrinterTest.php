<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Generator;
use Phel\Printer\TypePrinter\StringPrinter;
use PHPUnit\Framework\TestCase;

final class StringPrinterTest extends TestCase
{
    public function test_print_no_readable(): void
    {
        self::assertSame('str', (new StringPrinter(false))->print('str'));
    }

    /**
     * @dataProvider printerDataProvider
     */
    public function test_print_readable(string $expected, string $string): void
    {
        self::assertSame(
            $expected,
            (new StringPrinter(true))->print($string)
        );
    }

    public function printerDataProvider(): Generator
    {
        yield 'str' => [
            'expected' => '"str"',
            'string' => 'str',
        ];

        yield 'characters U-00000080 - U-000007FF, mask 110XXXXX' => [
            'expected' => '"\u{0100}"',
            'string' => "\u{100}",
        ];

        yield 'characters U-00000800 - U-0000FFFF, mask 1110XXXX' => [
            'expected' => '"\u{1100}"',
            'string' => "\u{1100}",
        ];

        yield 'characters U-00010000 - U-001FFFFF, mask 11110XXX' => [
            'expected' => '"\u{11110}"',
            'string' => "\u{11110}",
        ];
    }
}
