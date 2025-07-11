<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Generator;
use Phel\Printer\TypePrinter\BooleanPrinter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BooleanPrinterTest extends TestCase
{
    #[DataProvider('printerDataProvider')]
    public function test_print(string $expected, bool $boolean): void
    {
        self::assertSame(
            $expected,
            (new BooleanPrinter())->print($boolean),
        );
    }

    public static function printerDataProvider(): Generator
    {
        yield [
            'true',
            true,
        ];

        yield [
            'false',
            false,
        ];
    }
}
