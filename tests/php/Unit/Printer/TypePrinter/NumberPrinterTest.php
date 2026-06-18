<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Generator;
use Phel\Shared\Printer\TypePrinter\NumberPrinter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class NumberPrinterTest extends TestCase
{
    #[DataProvider('printerDataProvider')]
    public function test_print(string $expected, float|int $number): void
    {
        self::assertSame(
            $expected,
            new NumberPrinter()->print($number),
        );
    }

    public static function printerDataProvider(): Generator
    {
        yield 'integer' => [
            '1',
            1,
        ];

        yield 'float' => [
            '1.02',
            1.02,
        ];

        yield 'nan' => [
            'NAN',
            NAN,
        ];

        yield 'positive infinity' => [
            'INF',
            INF,
        ];

        yield 'negative infinity' => [
            '-INF',
            -INF,
        ];
    }

    public function test_print_nan_does_not_emit_a_php_warning(): void
    {
        set_error_handler(static function (int $errno, string $errstr): bool {
            throw new RuntimeException($errstr);
        });

        try {
            self::assertSame('NAN', new NumberPrinter()->print(NAN));
        } finally {
            restore_error_handler();
        }
    }
}
