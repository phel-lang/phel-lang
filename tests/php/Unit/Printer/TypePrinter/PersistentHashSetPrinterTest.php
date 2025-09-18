<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Generator;
use Phel;
use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;
use Phel\Printer\Printer;
use Phel\Printer\TypePrinter\PersistentHashSetPrinter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PersistentHashSetPrinterTest extends TestCase
{
    #[DataProvider('printerDataProvider')]
    public function test_print(string $expected, PersistentHashSetInterface $set): void
    {
        self::assertSame(
            $expected,
            (new PersistentHashSetPrinter(Printer::readable()))->print($set),
        );
    }

    public static function printerDataProvider(): Generator
    {
        yield 'empty set' => [
            '#{}',
            Phel::set(),
        ];

        yield 'set with values' => [
            '#{1 2}',
            Phel::set([1, 2]),
        ];
    }
}
