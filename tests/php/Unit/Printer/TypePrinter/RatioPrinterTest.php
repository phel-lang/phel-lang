<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Generator;
use Phel\Lang\Ratio;
use Phel\Shared\Printer\Printer;
use Phel\Shared\Printer\TypePrinter\RatioPrinter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RatioPrinterTest extends TestCase
{
    #[DataProvider('printerDataProvider')]
    public function test_print(string $expected, Ratio $rational): void
    {
        self::assertSame(
            $expected,
            new RatioPrinter()->print($rational),
        );
    }

    public static function printerDataProvider(): Generator
    {
        $half = Ratio::create(1, 2);
        self::assertInstanceOf(Ratio::class, $half);
        yield 'one half' => ['1/2', $half];

        $negativeThirteenths = Ratio::create(-3, 13);
        self::assertInstanceOf(Ratio::class, $negativeThirteenths);
        yield 'negative' => ['-3/13', $negativeThirteenths];

        $reduced = Ratio::create(6, 4);
        self::assertInstanceOf(Ratio::class, $reduced);
        yield 'reduced form' => ['3/2', $reduced];
    }

    public function test_printer_dispatch_readable(): void
    {
        $rational = Ratio::create(7, 9);

        self::assertSame('7/9', Printer::readable()->print($rational));
    }

    public function test_printer_dispatch_non_readable(): void
    {
        $rational = Ratio::create(7, 9);

        self::assertSame('7/9', Printer::nonReadable()->print($rational));
    }
}
