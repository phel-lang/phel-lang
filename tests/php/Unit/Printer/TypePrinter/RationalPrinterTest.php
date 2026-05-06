<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Generator;
use Phel\Lang\Rational;
use Phel\Printer\Printer;
use Phel\Printer\TypePrinter\RationalPrinter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RationalPrinterTest extends TestCase
{
    #[DataProvider('printerDataProvider')]
    public function test_print(string $expected, Rational $rational): void
    {
        self::assertSame(
            $expected,
            new RationalPrinter()->print($rational),
        );
    }

    public static function printerDataProvider(): Generator
    {
        $half = Rational::create(1, 2);
        self::assertInstanceOf(Rational::class, $half);
        yield 'one half' => ['1/2', $half];

        $negativeThirteenths = Rational::create(-3, 13);
        self::assertInstanceOf(Rational::class, $negativeThirteenths);
        yield 'negative' => ['-3/13', $negativeThirteenths];

        $reduced = Rational::create(6, 4);
        self::assertInstanceOf(Rational::class, $reduced);
        yield 'reduced form' => ['3/2', $reduced];
    }

    public function test_printer_dispatch_readable(): void
    {
        $rational = Rational::create(7, 9);

        self::assertSame('7/9', Printer::readable()->print($rational));
    }

    public function test_printer_dispatch_non_readable(): void
    {
        $rational = Rational::create(7, 9);

        self::assertSame('7/9', Printer::nonReadable()->print($rational));
    }
}
