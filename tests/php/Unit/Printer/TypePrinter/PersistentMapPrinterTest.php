<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Generator;
use Phel;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Printer\Printer;
use Phel\Printer\TypePrinter\PersistentMapPrinter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PersistentMapPrinterTest extends TestCase
{
    #[DataProvider('printerDataProvider')]
    public function test_print(string $expected, PersistentMapInterface $map): void
    {
        self::assertSame(
            $expected,
            (new PersistentMapPrinter(Printer::readable()))->print($map),
        );
    }

    public static function printerDataProvider(): Generator
    {
        yield 'empty map' => [
            '{}',
            Phel::map(),
        ];

        yield 'map with values' => [
            '{:a 1, :b 2}',
            Phel::map(
                Phel::keyword('a'),
                1,
                Phel::keyword('b'),
                2,
            ),
        ];
    }
}
